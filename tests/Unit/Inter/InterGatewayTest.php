<?php

use PHPay\Contracts\{Capability, SupportsCharges, SupportsWebhookEvents, SupportsWebhooks};
use PHPay\Exceptions\NotImplementedException;
use PHPay\Http\Certificate;
use PHPay\Inter\InterGateway;
use PHPay\Inter\Resources\Charge\Charge;
use PHPay\Inter\Resources\Webhook\Webhook;

it('declara cobrança, webhook e leitura de evento, e nada além', function () {
    $gateway = new InterGateway('id', 'secret');

    expect($gateway)->toBeInstanceOf(SupportsCharges::class)
        ->and($gateway)->toBeInstanceOf(SupportsWebhooks::class)
        ->and($gateway)->toBeInstanceOf(SupportsWebhookEvents::class);

    $phpay = PHPay\PHPay::gateway($gateway);

    expect($phpay->supports(Capability::CHARGES))->toBeTrue()
        ->and($phpay->supports(Capability::WEBHOOKS))->toBeTrue()
        ->and($phpay->supports(Capability::WEBHOOK_EVENTS))->toBeTrue()
        ->and($phpay->supports(Capability::CUSTOMERS))->toBeFalse()
        ->and($phpay->supports(Capability::SUBSCRIPTIONS))->toBeFalse()
        ->and($phpay->supports(Capability::PIX_KEYS))->toBeFalse()
        ->and($phpay->supports(Capability::WEBHOOK_SIGNATURE))->toBeFalse()
        ->and($phpay->supports(Capability::REFUNDS))->toBeFalse();
})->group('inter');

it('não declara estorno, pela mesma razão da efí', function () {
    /*
     * a devolução existe na API (`PUT /pix/{e2eId}/devolucao/{id}`), mas exige
     * valor e é endereçada pelo endToEndId — a capacidade promete que valor
     * ausente estorna integral. Fica no Resource, não na capacidade.
     */
    $phpay = PHPay\PHPay::gateway(new InterGateway('id', 'secret'));

    expect(fn () => $phpay->refund('qualquer-id'))->toThrow(NotImplementedException::class)
        ->and(method_exists(Charge::class, 'refund'))->toBeTrue();
})->group('inter');

it('não faz chamada de rede ao instanciar o gateway', function () {
    /* o token é buscado no primeiro uso, não no construtor */
    expect(new InterGateway('id', 'secret'))->toBeInstanceOf(InterGateway::class);
})->group('inter');

it('pede a união dos escopos que as capacidades usam', function () {
    $escopos = explode(' ', InterGateway::SCOPES);

    sort($escopos);

    expect($escopos)->toBe([
        'cob.read', 'cob.write',
        'cobv.read', 'cobv.write',
        'pix.read', 'pix.write',
        'webhook.read', 'webhook.write',
    ]);

    /*
     * lotecobv e payloadlocation ficam fora de propósito: nenhum recurso daqui
     * chama esses endpoints, e pedir escopo que a aplicação não tem reprova a
     * requisição inteira do token.
     */
    expect(InterGateway::SCOPES)->not->toContain('lotecobv')
        ->and(InterGateway::SCOPES)->not->toContain('payloadlocation');
})->group('inter');

it('aceita escopo próprio para aplicação com permissão mais estreita', function () {
    $gateway = new InterGateway('id', 'secret', true, null, 'cob.read');

    expect($gateway->scopes())->toBe('cob.read')
        ->and((new InterGateway('id', 'secret'))->scopes())->toBe(InterGateway::SCOPES)
        ->and((new InterGateway('id', 'secret', true, null, '   '))->scopes())->toBe(InterGateway::SCOPES);
})->group('inter');

it('troca credencial por token no primeiro uso e reaproveita depois', function () {
    /*
     * não é otimização: gerar token é limitado a 5 chamadas por minuto, e a
     * documentação do Inter manda reaproveitar por uma hora.
     */
    $history = [];
    $gateway = interGateway([
        jsonResponse(['txid' => 'a']),
        jsonResponse(['txid' => 'b']),
    ], $history);

    $gateway->charge()->find('a');
    $gateway->charge()->find('b');

    expect($history)->toHaveCount(3)
        ->and((string) $history[0]['request']->getUri())->toEndWith('oauth/v2/token')
        ->and((string) $history[1]['request']->getUri())->toContain('pix/v2/cob/a')
        ->and((string) $history[2]['request']->getUri())->toContain('pix/v2/cob/b');
})->group('inter');

it('renegocia o token quando ele expira', function () {
    $history = [];
    $gateway = new InterGateway(
        'id',
        'secret',
        true,
        client: interClient([
            interToken('tok_1', 0),
            jsonResponse(['txid' => 'a']),
            interToken('tok_2'),
            jsonResponse(['txid' => 'b']),
        ], $history),
    );

    $gateway->charge()->find('a');
    $gateway->charge()->find('b');

    /*
     * o Bearer não aparece aqui: client injetado não passa pelo boot do trait,
     * de propósito, então não carrega os headers. Quem prova o Bearer é o
     * ClientBootTest. O que este teste prova é a renegociação — token, cobrança,
     * token de novo, cobrança —, que é o que o `expires_in: 0` dispara.
     */
    expect($history)->toHaveCount(4)
        ->and((string) $history[0]['request']->getUri())->toEndWith('oauth/v2/token')
        ->and((string) $history[1]['request']->getUri())->toContain('pix/v2/cob/a')
        ->and((string) $history[2]['request']->getUri())->toEndWith('oauth/v2/token')
        ->and((string) $history[3]['request']->getUri())->toContain('pix/v2/cob/b');
})->group('inter');

it('devolve a instância de cada recurso que declara', function () {
    $gateway = interGateway([]);

    expect($gateway->charge())->toBeInstanceOf(Charge::class)
        ->and($gateway->webhook())->toBeInstanceOf(Webhook::class);
})->group('inter');

it('usa o domínio de sandbox, que é outro domínio e não outro caminho', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)->charge()->find('a');

    expect((string) $history[0]['request']->getUri())->toStartWith('https://cdpj-sandbox.partners.uatinter.co/')
        ->and((new InterGateway('id', 'secret'))->isSandbox())->toBeTrue()
        ->and((new InterGateway('id', 'secret', false))->isSandbox())->toBeFalse();
})->group('inter');

it('esconde as credenciais num dump', function () {
    $dump = print_r(new InterGateway('id-secreto', 'segredo-do-cliente'), true);

    expect($dump)->not->toContain('segredo-do-cliente')
        ->and($dump)->not->toContain('id-secreto')
        ->and($dump)->toContain('********');
})->group('inter');

it('aceita o certificado como caminho ou como objeto', function () {
    $path = sys_get_temp_dir() . '/phpay-inter-' . bin2hex(random_bytes(5)) . '.p12';
    file_put_contents($path, 'conteúdo');

    expect(new InterGateway('id', 'secret', true, $path))->toBeInstanceOf(InterGateway::class)
        ->and(new InterGateway('id', 'secret', true, new Certificate($path)))->toBeInstanceOf(InterGateway::class);

    unlink($path);
})->group('inter');

it('o nome do gateway é Inter', function () {
    expect((new InterGateway('id', 'secret'))->name())->toBe('Inter');
})->group('inter');
