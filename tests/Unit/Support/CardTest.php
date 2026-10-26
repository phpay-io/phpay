<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Card;

it('pega o dígito errado antes de sair para a rede', function () {
    expect(fn () => Card::make('4111 1111 1111 1112', 'MARIA OLIVEIRA', '07', '2030', '123'))
        ->toThrow(ValidationException::class, Card::messages()->number);
})->group('support');

it('aceita o número com a pontuação que veio do formulário', function () {
    expect(Card::make('4111 1111 1111 1111', 'MARIA OLIVEIRA', '07', '2030', '123')->number)
        ->toBe('4111111111111111');
})->group('support');

it('completa o mês com zero à esquerda', function () {
    expect(Card::make('4111111111111111', 'MARIA', '7', '2030', '123')->expirationMonth)->toBe('07');
})->group('support');

it('recusa mês, ano e cvv fora do formato', function (string $mes, string $ano, string $cvv) {
    expect(fn () => new Card('4111111111111111', 'MARIA', $mes, $ano, $cvv))
        ->toThrow(ValidationException::class);
})->with([
    ['13', '2030', '123'],
    ['00', '2030', '123'],
    ['07', '30', '123'],
    ['07', '2030', '12'],
    ['07', '2030', '12345'],
])->group('support');

it('recusa cartão sem nome impresso', function () {
    expect(fn () => new Card('4111111111111111', '   ', '07', '2030', '123'))
        ->toThrow(ValidationException::class, Card::messages()->holderName);
})->group('support');

it('nunca deixa o número nem o cvv num dump', function () {
    $saida = print_r(Card::make('4111111111111111', 'MARIA OLIVEIRA', '07', '2030', '123'), true);

    expect($saida)->not->toContain('4111111111111111')
        ->and($saida)->not->toContain('123')
        ->and($saida)->toContain('**** **** **** 1111');
})->group('support');

it('entrega a validade como a cielo quer', function () {
    expect(Card::make('4111111111111111', 'MARIA', '07', '2030', '123')->expiration())->toBe('07/2030');
})->group('support');
