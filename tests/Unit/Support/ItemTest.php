<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Support\{Item, Money};

it('recusa item sem nome', function () {
    expect(fn () => new Item('  ', Money::reais('10,00')))
        ->toThrow(ValidationException::class, Item::messages()->name);
})->group('support');

it('recusa quantidade menor que um', function () {
    expect(fn () => new Item('Camiseta', Money::reais('10,00'), 0))
        ->toThrow(ValidationException::class, Item::messages()->quantity);
})->group('support');

it('recusa preço unitário zerado', function () {
    expect(fn () => new Item('Camiseta', Money::centavos(0)))
        ->toThrow(ValidationException::class, Item::messages()->unitAmount);
})->group('support');

it('cobra a linha, não a unidade', function () {
    expect((new Item('Camiseta', Money::reais('54,90'), 2))->total()->toCentavos())->toBe(10980);
})->group('support');

it('soma a lista inteira', function () {
    $total = Item::sum([
        new Item('Camiseta', Money::reais('54,90'), 2),
        new Item('Caneca', Money::reais('40,00')),
    ]);

    expect($total->toCentavos())->toBe(14980)
        ->and($total->toReais())->toBe(149.80);
})->group('support');

it('recusa lista vazia, porque três gateways recusam cobrança sem item', function () {
    expect(fn () => Item::sum([]))->toThrow(ValidationException::class, Item::messages()->empty);
})->group('support');

it('recusa o que não é Item', function () {
    expect(fn () => Item::sum([['name' => 'Camiseta']]))
        ->toThrow(ValidationException::class, Item::messages()->type);
})->group('support');
