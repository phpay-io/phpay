<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Money;

it('converte entre reais e centavos sem perder valor', function (int|float|string $reais, int $centavos) {
    expect(Money::reais($reais)->toCentavos())->toBe($centavos)
        ->and(Money::centavos($centavos)->toReais())->toBe(round((float) str_replace(',', '.', (string) $reais), 2));
})->with([
    [100.50, 10050],
    [1, 100],
    [0.07, 7],
    [0.01, 1],
    [0, 0],
    ['100.50', 10050],
    ['100,50', 10050],
])->group('support');

it('entende o formato brasileiro com separador de milhar', function () {
    expect(Money::reais('1.234,56')->toCentavos())->toBe(123456)
        ->and(Money::reais('1234.56')->toCentavos())->toBe(123456);
})->group('support');

it('sobrevive à imprecisão de float', function () {
    /* 0.07 * 100 dá 7.000000000000001 em ponto flutuante */
    expect(Money::reais(0.07)->toCentavos())->toBe(7)
        ->and(Money::reais(0.29)->toCentavos())->toBe(29)
        ->and(Money::reais(1.15)->toCentavos())->toBe(115)
        ->and(Money::reais(19.99)->toCentavos())->toBe(1999);
})->group('support');

it('recusa mais de duas casas decimais em vez de arredondar calado', function () {
    expect(fn () => Money::reais(10 / 3))
        ->toThrow(ValidationException::class, 'duas casas decimais');

    expect(fn () => Money::reais(1.005))
        ->toThrow(ValidationException::class, 'duas casas decimais');
})->group('support');

it('recusa valor negativo', function () {
    expect(fn () => Money::reais(-1))->toThrow(ValidationException::class, 'não pode ser negativo');
    expect(fn () => Money::centavos(-1))->toThrow(ValidationException::class, 'não pode ser negativo');
})->group('support');

it('recusa string que não é número', function () {
    expect(fn () => Money::reais('cem reais'))
        ->toThrow(ValidationException::class, 'precisa ser numérico');
})->group('support');

it('multiplica e soma', function () {
    $unitario = Money::reais(59.90);

    expect($unitario->multiply(2)->toCentavos())->toBe(11980)
        ->and($unitario->plus(Money::reais(10))->toCentavos())->toBe(6990)
        ->and($unitario->multiply(0)->isZero())->toBeTrue();

    expect(fn () => $unitario->multiply(-1))
        ->toThrow(ValidationException::class, 'não pode ser negativa');
})->group('support');

it('é imutável: operações devolvem outro objeto', function () {
    $original = Money::reais(100);
    $dobro    = $original->multiply(2);

    expect($original->toCentavos())->toBe(10000)
        ->and($dobro->toCentavos())->toBe(20000)
        ->and($original)->not->toBe($dobro);
})->group('support');

it('compara por valor', function () {
    expect(Money::reais(100.50)->equals(Money::centavos(10050)))->toBeTrue()
        ->and(Money::reais(100.50)->equals(Money::reais(100.51)))->toBeFalse();
})->group('support');

it('formata do jeito que se lê no brasil', function () {
    expect(Money::reais(100.50)->format())->toBe('R$ 100,50')
        ->and(Money::centavos(1)->format())->toBe('R$ 0,01')
        ->and(Money::reais(1234.56)->format())->toBe('R$ 1.234,56');
})->group('support');

it('normaliza o que o gateway recebe, seja Money ou número cru', function () {
    /* int cru continua sendo lido como centavos, como antes do Money existir */
    expect(Money::asCentavos(10050))->toBe(10050)
        ->and(Money::asCentavos(Money::reais(100.50)))->toBe(10050);

    /* e como reais nos gateways que usam decimal */
    expect(Money::asReais(100.50))->toBe(100.50)
        ->and(Money::asReais(Money::centavos(10050)))->toBe(100.50);
})->group('support');

it('impede o erro que motivou o value object', function () {
    /*
    | R$ 100,50 num gateway de centavos: quem manda 100.50 cru cobra R$ 1,00.
    | Com Money, o mesmo objeto dá o número certo para cada unidade.
    */
    $valor = Money::reais(100.50);

    expect($valor->toCentavos())->toBe(10050)
        ->and($valor->toReais())->toBe(100.50);
})->group('support');
