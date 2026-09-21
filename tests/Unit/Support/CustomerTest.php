<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Customer;

it('distingue CPF de CNPJ pelo tamanho', function () {
    $pessoa  = new Customer('Mário Lucas', '12345678901');
    $empresa = new Customer('Sixtec LTDA', '12345678000199');
    $semDoc  = new Customer('Anônimo');

    expect($pessoa->isIndividual())->toBeTrue()
        ->and($pessoa->documentType())->toBe('CPF')
        ->and($empresa->isCompany())->toBeTrue()
        ->and($empresa->documentType())->toBe('CNPJ')
        ->and($semDoc->documentType())->toBeNull()
        ->and($semDoc->isIndividual())->toBeFalse();
})->group('support');

it('limpa pontuação de documento e telefone com make()', function () {
    $cliente = Customer::make(
        name: 'Mário Lucas',
        document: '123.456.789-01',
        phone: '(11) 94002-8922',
    );

    expect($cliente->document)->toBe('12345678901')
        ->and($cliente->phone)->toBe('11940028922');
})->group('support');

it('separa o nome como o mercado pago quer', function () {
    expect((new Customer('Mário Lucas da Silva'))->firstName())->toBe('Mário')
        ->and((new Customer('Mário Lucas da Silva'))->lastName())->toBe('Lucas da Silva')
        ->and((new Customer('Prince'))->lastName())->toBeNull();
})->group('support');

it('quebra o telefone como o pagbank quer', function () {
    expect((new Customer('Mário', phone: '11940028922'))->phoneParts())
        ->toBe(['country' => '55', 'area' => '11', 'number' => '940028922']);

    /* aceita o número já com o código do país */
    expect((new Customer('Mário', phone: '5511940028922'))->phoneParts())
        ->toBe(['country' => '55', 'area' => '11', 'number' => '940028922']);

    expect((new Customer('Mário'))->phoneParts())->toBeNull()
        ->and((new Customer('Mário', phone: '123'))->phoneParts())->toBeNull();
})->group('support');

it('recusa documento com tamanho inválido', function () {
    expect(fn () => new Customer('Mário', '123'))
        ->toThrow(ValidationException::class, '11 dígitos');
})->group('support');

it('recusa nome vazio e e-mail inválido', function () {
    expect(fn () => new Customer('   '))->toThrow(ValidationException::class, 'nome do cliente');
    expect(fn () => new Customer('Mário', email: 'nao-e-email'))
        ->toThrow(ValidationException::class, 'e-mail');
})->group('support');

it('é imutável: withExtra e withId devolvem cópias', function () {
    $original = new Customer('Mário Lucas', '12345678901');
    $comId    = $original->withId('cus_1');
    $comExtra = $original->withExtra(['observacao' => 'vip']);

    expect($original->id)->toBeNull()
        ->and($comId->id)->toBe('cus_1')
        ->and($original->extra())->toBe([])
        ->and($comExtra->extra())->toBe(['observacao' => 'vip'])
        ->and($comExtra->name)->toBe('Mário Lucas');
})->group('support');
