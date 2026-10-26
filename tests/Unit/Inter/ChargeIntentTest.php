<?php

use GuzzleHttp\Psr7\Response;
use PHPay\Contracts\{Capability, SupportsChargeIntents};
use PHPay\Exceptions\ValidationException;
use PHPay\Http\{Certificate, ClientFactory};
use PHPay\Inter\InterGateway;
use PHPay\PHPay;
use PHPay\Support\{ChargeIntent, Customer, Method, Money};

afterEach(function () {
    ClientFactory::stopFaking();
});

/**
 * a pair on disk, because the Inter needs mTLS on every request, the token
 * included — and the certificate is checked before any client is built.
 *
 * @return Certificate
 */
function interParDeTeste(): Certificate
{
    $crt = sys_get_temp_dir() . '/phpay-intent-' . bin2hex(random_bytes(5)) . '.crt';
    $key = sys_get_temp_dir() . '/phpay-intent-' . bin2hex(random_bytes(5)) . '.key';

    file_put_contents($crt, '-- certificado de teste --');
    file_put_contents($key, '-- chave de teste --');

    register_shutdown_function(static fn () => @unlink($crt) && @unlink($key));

    return Certificate::pair($crt, $key);
}

it('declara cobrança portável, só por Pix', function () {
    $gateway = new InterGateway('id', 'secret');

    expect($gateway)->toBeInstanceOf(SupportsChargeIntents::class)
        ->and($gateway->supportedMethods())->toBe([Method::PIX])
        ->and(PHPay::gateway($gateway)->supports(Capability::CHARGE_INTENTS))->toBeTrue();
})->group('inter');

it('recusa sem chave Pix, antes de qualquer HTTP', function () {
    ClientFactory::fake([]);

    $gateway = new InterGateway('id', 'secret', true, interParDeTeste());

    expect(fn () => $gateway->createCharge(ChargeIntent::pix(Money::reais('149,90'))))
        ->toThrow(ValidationException::class, InterGateway::messages()->pixKey)
        ->and(ClientFactory::recorded())->toBe([]);
})->group('inter');

it('monta o payload do bacen a partir do intent', function () {
    ClientFactory::fake([
        new Response(200, [], '{"access_token":"t","token_type":"Bearer","expires_in":3600}'),
        new Response(200, [], '{"txid":"abc"}'),
    ]);

    $gateway = new InterGateway('id', 'secret', true, interParDeTeste(), null, null, null, 'chave@exemplo.com');

    $gateway->createCharge(ChargeIntent::pix(
        amount: Money::reais('149,90'),
        customer: Customer::make(name: 'Maria Oliveira', document: '123.456.789-09'),
        description: 'Plano anual',
    ));

    $corpo = json_decode((string) ClientFactory::recorded()[1]['request']->getBody(), true);

    /* reais em STRING, que é o terceiro formato de valor entre os dez */
    expect($corpo['valor']['original'])->toBe('149.90')
        ->and($corpo['chave'])->toBe('chave@exemplo.com')
        ->and($corpo['devedor']['cpf'])->toBe('12345678909')
        ->and($corpo['solicitacaoPagador'])->toBe('Plano anual');
})->group('inter');

it('com vencimento, vai para cobv e não para cob', function () {
    ClientFactory::fake([
        new Response(200, [], '{"access_token":"t","token_type":"Bearer","expires_in":3600}'),
        new Response(200, [], '{"txid":"abc"}'),
    ]);

    $gateway = new InterGateway('id', 'secret', true, interParDeTeste(), null, null, null, 'chave@exemplo.com');

    $gateway->createCharge(ChargeIntent::pix(
        amount: Money::reais('149,90'),
        customer: Customer::make(name: 'Maria Oliveira', document: '123.456.789-09'),
        dueDate: '2099-11-10',
    ));

    $uri = (string) ClientFactory::recorded()[1]['request']->getUri();

    expect($uri)->toContain('/cobv/')
        ->and($uri)->not->toContain('/cob/');
})->group('inter');

it('recebe a chave pela configuração, pelo registry', function () {
    $gateway = PHPay::make('inter', [
        'clientId'     => 'id',
        'clientSecret' => 'secret',
        'pixKey'       => 'chave@exemplo.com',
    ]);

    expect($gateway->supportedMethods())->toBe([Method::PIX]);
})->group('inter');
