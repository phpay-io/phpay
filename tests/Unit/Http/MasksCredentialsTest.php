<?php

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\Asaas\AsaasGateway;
use PHPay\Cielo\CieloGateway;
use PHPay\Efi\EfiGateway;
use PHPay\Efi\Resources\Charge\Charge as EfiCharge;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagBank\PagBankGateway;
use PHPay\Rede\RedeGateway;
use PHPay\Woovi\WooviGateway;

/**
 * what var_dump() and print_r() would show of an object.
 *
 * @param object $object
 * @return string
 */
function dumped(object $object): string
{
    ob_start();
    var_dump($object);

    return print_r($object, true) . ob_get_clean();
}

it('não imprime a credencial ao inspecionar gateway ou recurso', function (Closure $montar) {
    $segredo = 'segredo-que-nao-pode-vazar';

    expect(dumped($montar($segredo)))->not->toContain($segredo)
        ->toContain('********');
})->with([
    'asaas'                 => [fn (string $s) => new AsaasGateway($s)],
    'asaas, recurso'        => [fn (string $s) => (new AsaasGateway($s))->charge()],
    'woovi'                 => [fn (string $s) => new WooviGateway($s)],
    'woovi, recurso'        => [fn (string $s) => (new WooviGateway($s))->charge()],
    'mercado pago'          => [fn (string $s) => new MercadoPagoGateway("TEST-{$s}")],
    'mercado pago, recurso' => [fn (string $s) => (new MercadoPagoGateway("TEST-{$s}"))->charge()],
    'pagbank'               => [fn (string $s) => new PagBankGateway($s)],
    'pagbank, recurso'      => [fn (string $s) => (new PagBankGateway($s))->charge()],
    'pagar.me'              => [fn (string $s) => new PagarMeGateway("sk_test_{$s}")],
    'pagar.me, recurso'     => [fn (string $s) => (new PagarMeGateway("sk_test_{$s}"))->charge()],
    'abacatepay'            => [fn (string $s) => new AbacatePayGateway($s)],
    'abacatepay, recurso'   => [fn (string $s) => (new AbacatePayGateway($s))->charge()],
    'cielo'                 => [fn (string $s) => new CieloGateway('merchant-id', $s)],
    'cielo, recurso'        => [fn (string $s) => (new CieloGateway('merchant-id', $s))->charge()],
    'rede, com autorização' => [function (string $s) {
        $gateway = new RedeGateway('pv', $s);
        $gateway->charge();

        return $gateway;
    }],
    'efí'                     => [fn (string $s) => new EfiGateway('client-id', $s)],
    'efí, com token em cache' => [function (string $s) {
        $gateway = new EfiGateway('client-id', 'client-secret', true, mockClient([
            jsonResponse(['access_token' => $s, 'token_type' => 'Bearer', 'expires_in' => 3600]),
        ]));
        $gateway->getToken();

        return $gateway;
    }],
])->group('phpay');

it('esconde o client HTTP, que carrega a credencial nos headers', function () {
    $recurso = new EfiCharge(['access_token' => 'segredo-no-header', 'token_type' => 'Bearer']);

    expect(dumped($recurso))->not->toContain('segredo-no-header')
        ->toContain('[oculto: o client HTTP carrega a credencial]');
})->group('efi');

it('mantém visível o que não é credencial', function () {
    expect((new AsaasGateway('token', false))->__debugInfo())->toMatchArray(['sandbox' => false]);
})->group('asaas');
