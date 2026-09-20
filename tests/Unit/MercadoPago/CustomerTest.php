<?php

use PHPay\Exceptions\ValidationException;
use PHPay\MercadoPago\Resources\Customer\Customer;

it('cria um cliente', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 'cus_1', 'email' => 'fale@phpay.io'])], $history);

    (new Customer('TEST-token', ['email' => 'fale@phpay.io', 'first_name' => 'Mário'], $client))->create();

    expect((string) $history[0]['request']->getUri())->toEndWith('/v1/customers')
        ->and(recordedBody($history))->toBe([
            'email'      => 'fale@phpay.io',
            'first_name' => 'Mário',
        ]);
})->group('mercadopago');

it('exige e-mail válido para criar o cliente', function (mixed $email) {
    $history = [];
    $client  = mpClient([jsonResponse([])], $history);

    expect(fn () => (new Customer('TEST-token', ['email' => $email], $client))->create())
        ->toThrow(ValidationException::class, 'e-mail válido');

    expect($history)->toBeEmpty();
})->with(['nao-e-email', '', 12345])->group('mercadopago');

it('encontra um cliente por e-mail', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['results' => [['id' => 'cus_1', 'email' => 'fale@phpay.io']]])], $history);

    $encontrado = (new Customer('TEST-token', [], $client))->findByEmail('fale@phpay.io');

    expect($encontrado['id'])->toBe('cus_1')
        ->and((string) $history[0]['request']->getUri())->toContain('email=fale%40phpay.io');
})->group('mercadopago');

it('devolve null quando nenhum cliente casa com o e-mail', function () {
    $client = mpClient([jsonResponse(['results' => []])]);

    expect((new Customer('TEST-token', [], $client))->findByEmail('ninguem@phpay.io'))->toBeNull();
})->group('mercadopago');

it('não expõe exclusão de cliente, que a api não oferece', function () {
    expect(method_exists(Customer::class, 'destroy'))->toBeFalse();
})->group('mercadopago');
