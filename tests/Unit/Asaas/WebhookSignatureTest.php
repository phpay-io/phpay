<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Exceptions\ValidationException;

/**
 * verificador do Asaas, já com o token configurado.
 *
 * @param string $token
 * @return object
 */
function asaasVerifier(string $token = 'token-do-webhook-com-32-caracteres'): object
{
    return (new AsaasGateway('api-key', true, mockClient([])))
        ->webhookSignature()
        ->setSecret($token);
}

it('aceita a notificação quando o token do header bate', function (array $headers) {
    expect(asaasVerifier()->setHeaders($headers)->verify())->toBeTrue();
})->with([
    'header cru'  => [['asaas-access-token' => 'token-do-webhook-com-32-caracteres']],
    'do $_SERVER' => [['HTTP_ASAAS_ACCESS_TOKEN' => 'token-do-webhook-com-32-caracteres']],
])->group('asaas');

it('recusa a notificação quando o token não bate', function () {
    expect(asaasVerifier()->setHeaders(['asaas-access-token' => 'outro'])->verify())->toBeFalse();
})->group('asaas');

it('não aceita por engano quando o header não veio', function () {
    expect(fn () => asaasVerifier()->setHeaders(['content-type' => 'application/json'])->verify())
        ->toThrow(ValidationException::class, 'asaas-access-token');
})->group('asaas');

it('exige o segredo antes de verificar', function () {
    $gateway = new AsaasGateway('api-key', true, mockClient([]));

    expect(fn () => $gateway->webhookSignature()->setHeaders([])->verify())
        ->toThrow(ValidationException::class, 'setSecret()');
})->group('asaas');

it('não imprime o token do webhook ao inspecionar o verificador', function () {
    $dump = print_r(asaasVerifier(), true);

    expect($dump)->toContain('********')
        ->and($dump)->not->toContain('token-do-webhook-com-32-caracteres');
})->group('asaas');
