<?php

use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Exception\{ConnectException, RequestException, ServerException};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response};
use PHPay\Http\{ClientFactory, RetryPolicy};
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * falha de transporte sem resposta, como o Guzzle 8 entrega: implementa o
 * contrato do PSR-18, não tem getHandlerContext() e não é ConnectException.
 *
 * É o que a NetworkException e a NetworkTimeoutException do Guzzle 8 são para
 * a política — a requisição pode ter saído, então escrita não se repete.
 */
final class FalhaDeRedeSemContexto extends RuntimeException implements NetworkExceptionInterface
{
    /**
     * @param RequestInterface $request
     */
    public function __construct(private RequestInterface $request)
    {
        parent::__construct('falha de transporte sem resposta');
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}

/**
 * a request, with the headers a gateway would see.
 *
 * @param string $method
 * @param array<string, string> $headers
 * @return Request
 */
function retryRequest(string $method = 'POST', array $headers = []): Request
{
    return new Request($method, 'https://gateway.test/orders', $headers);
}

/**
 * the failure Guzzle raises for a transport error, with the cURL details it
 * carries — the errno is the only thing that says whether anything was sent.
 *
 * @param int $errno
 * @param float $connectTime seconds spent connecting, 0 when it never did
 * @return ConnectException
 */
function connectError(int $errno, float $connectTime = 0.0): ConnectException
{
    return new ConnectException(
        'cURL error ' . $errno,
        retryRequest(),
        null,
        ['errno' => $errno, 'connect_time' => $connectTime]
    );
}

/**
 * client from the factory, answering from a canned queue.
 *
 * @param array<int, Response|Throwable> $responses
 * @param array<int, RequestInterface> $seen filled with every attempt made
 * @param RetryPolicy|null $policy
 * @return Client
 */
function retryClient(array $responses, array &$seen = [], ?RetryPolicy $policy = null): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));

    /* no delay: the waiting is the policy's business, tested on its own */
    $client = ClientFactory::make(
        ['handler' => $stack, 'base_uri' => 'https://gateway.test/'],
        $policy ?? new RetryPolicy(3, 0, 0)
    );

    /*
     * the factory pushes the retry last, which makes it the innermost. The
     * recorder goes on after that, so it sits INSIDE the retry and sees each
     * attempt — pushed before, it would see the outer call only once.
     */
    $stack->push(function (callable $handler) use (&$seen): callable {
        return function (RequestInterface $request, array $options) use ($handler, &$seen) {
            $seen[] = $request;

            return $handler($request, $options);
        };
    });

    return $client;
}

it('para de repetir quando as tentativas acabam', function () {
    $policy = new RetryPolicy();

    expect($policy->shouldRetry(0, retryRequest('GET'), new Response(503)))->toBeTrue()
        ->and($policy->shouldRetry(1, retryRequest('GET'), new Response(503)))->toBeTrue()
        ->and($policy->shouldRetry(2, retryRequest('GET'), new Response(503)))->toBeFalse();
})->group('phpay');

it('repete leitura em erro do servidor, nunca escrita', function (string $metodo, bool $repete) {
    expect((new RetryPolicy())->shouldRetry(0, retryRequest($metodo), new Response(500)))
        ->toBe($repete);
})->with([
    'GET'     => ['GET', true],
    'HEAD'    => ['HEAD', true],
    'OPTIONS' => ['OPTIONS', true],
    'POST'    => ['POST', false],
    /* o HTTP chama PUT e DELETE de idempotentes; a doc do Asaas discorda */
    'PUT'    => ['PUT', false],
    'PATCH'  => ['PATCH', false],
    'DELETE' => ['DELETE', false],
])->group('phpay');

it('repete escrita em erro do servidor quando há chave de idempotência', function (string $header) {
    $request = retryRequest('POST', [$header => 'chave-da-operacao']);

    expect((new RetryPolicy())->shouldRetry(0, $request, new Response(500)))->toBeTrue();
})->with([
    'Idempotency-Key',
    'X-Idempotency-Key',
    'x-idempotency-key',
    'IDEMPOTENCY-KEY',
])->group('phpay');

it('não aceita chave vazia como chave', function (string $valor) {
    $request = retryRequest('POST', ['Idempotency-Key' => $valor]);

    expect((new RetryPolicy())->shouldRetry(0, $request, new Response(500)))->toBeFalse();
})->with(['vazia' => [''], 'só espaço' => ['   ']])->group('phpay');

it('repete 429 em qualquer método, porque o rate limit recusa antes de processar', function (string $metodo) {
    expect((new RetryPolicy())->shouldRetry(0, retryRequest($metodo), new Response(429)))->toBeTrue();
})->with(['GET', 'POST', 'DELETE'])->group('phpay');

it('não repete o que o gateway entendeu e recusou', function (int $status) {
    expect((new RetryPolicy())->shouldRetry(0, retryRequest('GET'), new Response($status)))
        ->toBeFalse();
})->with([400, 401, 403, 404, 409, 422])->group('phpay');

it('repete escrita quando a requisição não saiu da máquina', function (int $errno) {
    expect((new RetryPolicy())->shouldRetry(0, retryRequest('POST'), null, connectError($errno)))
        ->toBeTrue();
})->with([
    'proxy não resolvido' => [5],
    'host não resolvido'  => [6],
    'não conectou'        => [7],
    'handshake TLS'       => [35],
])->group('phpay');

it('não repete escrita em timeout com conexão já estabelecida', function () {
    /* o corpo pode ter sido processado: repetir aqui cobra de novo */
    $timeout = connectError(28, 0.42);
    $policy  = new RetryPolicy();

    expect($policy->shouldRetry(0, retryRequest('POST'), null, $timeout))->toBeFalse()
        ->and($policy->shouldRetry(0, retryRequest('GET'), null, $timeout))->toBeTrue();
})->skip(
    !method_exists(ConnectException::class, 'getHandlerContext'),
    'cenário só existe no Guzzle 7: no 8, timeout com conexão aberta é NetworkTimeoutException, não ConnectException'
)->group('phpay');

it('repete escrita em timeout que nem conectou', function () {
    expect((new RetryPolicy())->shouldRetry(0, retryRequest('POST'), null, connectError(28, 0.0)))
        ->toBeTrue();
})->group('phpay');

it('trata servidor que fechou sem responder como requisição enviada', function () {
    /* CURLE_GOT_NOTHING: chegou lá, só não voltou resposta */
    $nada = connectError(52, 0.3);

    expect((new RetryPolicy())->shouldRetry(0, retryRequest('POST'), null, $nada))->toBeFalse()
        ->and((new RetryPolicy())->shouldRetry(0, retryRequest('GET'), null, $nada))->toBeTrue();
})->skip(
    !method_exists(ConnectException::class, 'getHandlerContext'),
    'cenário só existe no Guzzle 7: no 8, timeout com conexão aberta é NetworkTimeoutException, não ConnectException'
)->group('phpay');

it('trata falha de rede sem contexto como requisição enviada', function () {
    /*
     * é o caso do Guzzle 8: a classe diz "falhou na rede" e nada mais. Sem
     * prova de que não saiu, escrita não se repete — leitura sim.
     */
    $falha  = new FalhaDeRedeSemContexto(retryRequest('POST'));
    $policy = new RetryPolicy();

    expect($policy->shouldRetry(0, retryRequest('POST'), null, $falha))->toBeFalse()
        ->and($policy->shouldRetry(0, retryRequest('GET'), null, $falha))->toBeTrue();
})->group('phpay');

it('no Guzzle 7, ConnectException sem errno não prova que nada saiu', function () {
    /* lá a classe é o apanhado de tudo: sem o errno, não há o que afirmar */
    $sem = new ConnectException('erro de transporte', retryRequest('POST'));

    expect((new RetryPolicy())->shouldRetry(0, retryRequest('POST'), null, $sem))->toBeFalse();
})->skip(
    !method_exists(ConnectException::class, 'getHandlerContext'),
    'só vale no Guzzle 7: no 8 a classe por si já significa que não saiu'
)->group('phpay');

it('no Guzzle 8, ConnectException por si já significa que nada saiu', function () {
    /*
     * o 8 removeu o getHandlerContext() e separou os casos em classes: lá a
     * ConnectException é só falha de estabelecer conexão, e a
     * ConnectTimeoutException herda dela. Repetir um POST aqui é seguro.
     */
    $naoSaiu = new ConnectException('conexão recusada', retryRequest('POST'));

    expect((new RetryPolicy())->shouldRetry(0, retryRequest('POST'), null, $naoSaiu))->toBeTrue();
})->skip(
    method_exists(ConnectException::class, 'getHandlerContext'),
    'só vale no Guzzle 8: no 7 a classe não distingue nada sozinha'
)->group('phpay');

it('não adivinha diante de outra falha de transporte', function () {
    $outra = new RequestException('erro qualquer', retryRequest('GET'));

    expect((new RetryPolicy())->shouldRetry(0, retryRequest('GET'), null, $outra))->toBeFalse();
})->group('phpay');

it('desiste quando o gateway pede mais tempo do que a política aceita', function () {
    $policy = new RetryPolicy(3, 200, 2000);

    expect($policy->shouldRetry(0, retryRequest('GET'), new Response(429, ['Retry-After' => '30'])))
        ->toBeFalse()
        ->and($policy->shouldRetry(0, retryRequest('GET'), new Response(429, ['Retry-After' => '1'])))
        ->toBeTrue();
})->group('phpay');

it('desabilitada, não repete nada', function () {
    expect(RetryPolicy::disabled()->shouldRetry(0, retryRequest('GET'), new Response(503)))
        ->toBeFalse();
})->group('phpay');

it('cresce o atraso e respeita o teto depois do jitter', function () {
    $policy = new RetryPolicy(5, 200, 2000);

    expect($policy->delayFor(1))->toBeGreaterThanOrEqual(200)->toBeLessThanOrEqual(300)
        ->and($policy->delayFor(2))->toBeGreaterThanOrEqual(400)->toBeLessThanOrEqual(600)
        ->and($policy->delayFor(9))->toBe(2000);
})->group('phpay');

it('obedece ao Retry-After, em segundos ou em data', function () {
    $policy = new RetryPolicy(3, 200, 60000);

    expect($policy->delayFor(1, new Response(429, ['Retry-After' => '5'])))->toBe(5000)
        ->and($policy->delayFor(1, new Response(503, ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 10)])))
        ->toBeGreaterThanOrEqual(9000)
        ->and($policy->delayFor(1, new Response(503, ['Retry-After' => 'ontem'])))
        ->toBeGreaterThanOrEqual(200);
})->group('phpay');

it('o client da fábrica repete a leitura e devolve a resposta boa', function () {
    $seen   = [];
    $client = retryClient([new Response(503), jsonResponse(['ok' => true])], $seen);

    expect($client->get('ping')->getStatusCode())->toBe(200)
        ->and($seen)->toHaveCount(2);
})->group('phpay');

it('o client da fábrica não repete escrita sem chave', function () {
    $seen   = [];
    $client = retryClient([new Response(503), jsonResponse(['id' => 1])], $seen);

    expect(fn () => $client->post('orders', ['json' => []]))->toThrow(ServerException::class);
    expect($seen)->toHaveCount(1);
})->group('phpay');

it('repete a escrita com chave e manda a mesma chave nas duas tentativas', function () {
    $seen   = [];
    $client = retryClient([new Response(503), jsonResponse(['id' => 1])], $seen);

    $client->post('orders', [
        'headers' => ['Idempotency-Key' => 'chave-da-operacao'],
        'json'    => ['amount' => 1050],
    ]);

    /* chave nova na segunda tentativa seria uma segunda cobrança */
    expect($seen)->toHaveCount(2)
        ->and($seen[0]->getHeaderLine('Idempotency-Key'))->toBe('chave-da-operacao')
        ->and($seen[1]->getHeaderLine('Idempotency-Key'))->toBe('chave-da-operacao');
})->group('phpay');

it('o client da fábrica aceita política desabilitada', function () {
    $seen   = [];
    $client = retryClient([new Response(503), jsonResponse(['ok' => true])], $seen, RetryPolicy::disabled());

    expect(fn () => $client->get('ping'))->toThrow(ServerException::class);
    expect($seen)->toHaveCount(1);
})->group('phpay');
