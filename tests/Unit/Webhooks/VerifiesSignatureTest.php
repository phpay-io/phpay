<?php

use PHPay\Contracts\{Capability, SupportsWebhookSignature};
use PHPay\Exceptions\{NotImplementedException, ValidationException};
use PHPay\PHPay;
use PHPay\Support\Gateway;
use PHPay\Webhooks\VerifiesSignature;

it('todo verificador cumpre o contrato comum', function (Gateway $gateway) {
    /*
     * varre o enum: gateway que declare a capacidade e cujo verificador não
     * cumpra o contrato reprova aqui. É o que impede o `object` de voltar.
     */
    if (!is_subclass_of($gateway->gatewayClass(), SupportsWebhookSignature::class)) {
        expect(true)->toBeTrue();

        return;
    }

    $verifier = $gateway->make(array_fill_keys($gateway->credentials(), 'fake'))->webhookSignature();

    expect($verifier)->toBeInstanceOf(VerifiesSignature::class);
})->with(Gateway::cases())->group('phpay');

it('dá para encadear e verificar sem conhecer o gateway concreto', function () {
    /*
     * o caso que motivou a issue: quem resolveu o gateway por nome chega ao
     * verificador pela facade, e antes recebia `object` — sem nada para tipar.
     */
    $phpay = PHPay::make('asaas', ['token' => 'fake']);

    $ok = $phpay->webhookSignature()
        ->setSecret('segredo')
        ->setPayload('{"event":"PAYMENT_RECEIVED"}')
        ->setHeaders(['asaas-access-token' => 'segredo'])
        ->verify();

    expect($ok)->toBeTrue();
})->group('phpay');

it('responde false para assinatura que não bate, sem levantar', function () {
    $phpay = PHPay::make('asaas', ['token' => 'fake']);

    expect($phpay->webhookSignature()
        ->setSecret('segredo')
        ->setPayload('{}')
        ->setHeaders(['asaas-access-token' => 'outro'])
        ->verify())->toBeFalse();
})->group('phpay');

it('o contrato carrega só o que os cinco têm em comum', function () {
    $metodos = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass(VerifiesSignature::class))->getMethods()
    );

    sort($metodos);

    expect($metodos)->toBe(['setHeaders', 'setPayload', 'setSecret', 'verify']);
})->group('phpay');

it('setter de esquema específico fica fora do contrato', function () {
    /*
     * `setDataId`, `setQuery`, `setSignatureKey` e `setPublicKeys` pertencem a
     * um esquema cada, não ao contrato. Quem precisa deles pergunta com
     * method_exists() — é o que o bridge de Laravel faz.
     */
    foreach (['setDataId', 'setQuery', 'setSignatureKey', 'setPublicKeys'] as $metodo) {
        expect(method_exists(VerifiesSignature::class, $metodo))->toBeFalse();
    }

    $mercadoPago = PHPay::make('mercadopago', ['accessToken' => 'TEST-fake'])->webhookSignature();
    $woovi       = PHPay::make('woovi', ['appId' => 'fake'])->webhookSignature();

    expect(method_exists($mercadoPago, 'setDataId'))->toBeTrue()
        ->and(method_exists($woovi, 'setPublicKeys'))->toBeTrue()
        ->and(method_exists($mercadoPago, 'setPublicKeys'))->toBeFalse();
})->group('phpay');

it('o gateway que não assina continua recusando pela facade', function () {
    /* estreitar o retorno não transformou ausência de capacidade em contrato */
    $cielo = PHPay::make('cielo', ['merchantId' => 'm', 'merchantKey' => 'k']);

    expect($cielo->supports(Capability::WEBHOOK_SIGNATURE))->toBeFalse()
        ->and(fn () => $cielo->webhookSignature())
        ->toThrow(NotImplementedException::class);
})->group('phpay');

it('a capacidade continua prometendo object, para não quebrar implementador', function () {
    /*
     * estreitar o retorno da *interface* seria compatível para quem consome e
     * fatal para quem implementa: tipo de retorno é covariante, então gateway
     * próprio declarando `: object` pararia de carregar. Gateway próprio é
     * cenário suportado, e isso não é mudança de minor.
     */
    $retorno = (new ReflectionMethod(SupportsWebhookSignature::class, 'webhookSignature'))->getReturnType();

    expect((string) $retorno)->toBe('object');
})->group('phpay');

it('a facade confere o contrato que a capacidade não exige', function () {
    /* gateway próprio que devolva qualquer objeto é recusado com o motivo */
    $proprio = new class () implements SupportsWebhookSignature {
        public function name(): string
        {
            return 'Privado';
        }

        public function webhookSignature(): object
        {
            return new stdClass();
        }
    };

    expect(fn () => PHPay::gateway($proprio)->webhookSignature())
        ->toThrow(ValidationException::class, 'não cumpre');
})->group('phpay');
