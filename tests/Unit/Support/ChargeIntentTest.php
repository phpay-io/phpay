<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Support\{ChargeIntent, Customer, Method, Money};

it('recusa valor zero', function () {
    expect(fn () => ChargeIntent::pix(Money::centavos(0)))
        ->toThrow(ValidationException::class, ChargeIntent::messages()->amount);
})->group('support');

it('recusa data que não é Y-m-d', function (string $data) {
    expect(fn () => ChargeIntent::pix(Money::reais('10,00'), dueDate: $data))
        ->toThrow(ValidationException::class, ChargeIntent::messages()->dueDate);
})->with(['10/11/2026', '2026-13-01', '2026-11-31', 'amanhã'])->group('support');

it('recusa vencimento no passado', function () {
    expect(fn () => ChargeIntent::pix(Money::reais('10,00'), dueDate: '2020-01-01'))
        ->toThrow(ValidationException::class, ChargeIntent::messages()->dueDatePast);
})->group('support');

it('aceita Pix sem vencimento, porque cob sem vencimento é válido', function () {
    $intent = ChargeIntent::pix(Money::reais('149,90'));

    expect($intent->dueDate)->toBeNull()
        ->and($intent->method)->toBe(Method::PIX)
        ->and($intent->amount->toCentavos())->toBe(14990);
})->group('support');

it('é imutável: withExtra e withDefaultReference devolvem outro', function () {
    $intent = ChargeIntent::pix(Money::reais('10,00'));
    $outro  = $intent->withExtra(['soft' => 'LOJA'])->withDefaultReference('abc');

    expect($intent->extra())->toBe([])
        ->and($intent->reference)->toBeNull()
        ->and($outro->extra())->toBe(['soft' => 'LOJA'])
        ->and($outro->reference)->toBe('abc');
})->group('support');

it('withDefaultReference não sobrescreve a referência escolhida por quem chama', function () {
    $intent = ChargeIntent::pix(Money::reais('10,00'), reference: 'meu-id');

    expect($intent->withDefaultReference('gerado')->reference)->toBe('meu-id');
})->group('support');

it('descreve a cobrança quando ninguém descreveu', function () {
    expect(ChargeIntent::pix(Money::reais('10,00'))->descriptionOrDefault())->toBe('Cobrança')
        ->and(ChargeIntent::pix(Money::reais('10,00'), description: 'Plano')->descriptionOrDefault())->toBe('Plano');
})->group('support');

it('recusa o método que o gateway não faz, nomeando o que ele faz', function () {
    $intent = ChargeIntent::pix(Money::reais('10,00'));

    expect(fn () => $intent->ensureSupportedBy('Rede', [Method::CREDIT_CARD]))
        ->toThrow(ValidationException::class, 'Aceita: cartão de crédito.');
})->group('support');

it('aceita o método que o gateway faz', function () {
    $intent = ChargeIntent::pix(Money::reais('10,00'));

    expect(fn () => $intent->ensureSupportedBy('Woovi', [Method::PIX]))->not->toThrow(ValidationException::class);
})->group('support');

it('guarda o cliente como veio, sem remapear', function () {
    $cliente = Customer::make(name: 'Maria', document: '123.456.789-09');

    expect(ChargeIntent::pix(Money::reais('10,00'), customer: $cliente)->customer?->document)->toBe('12345678909');
})->group('support');
