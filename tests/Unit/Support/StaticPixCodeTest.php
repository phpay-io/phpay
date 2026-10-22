<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Support\{Money, StaticPixCode};

/**
 * o vetor de teste do Manual de Padrões para Iniciação do Pix, do BACEN.
 */
const VETOR_BACEN = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

/**
 * percorre o TLV e devolve os campos de primeiro nível.
 *
 * ler de volta é o que prova que cada tamanho declarado bate com o valor: um
 * byte errado em qualquer campo desalinha todos os seguintes.
 *
 * @param string $payload
 * @return array<string, string>
 */
function camposDe(string $payload): array
{
    $campos = [];
    $i      = 0;

    while ($i < strlen($payload)) {
        $id          = substr($payload, $i, 2);
        $tamanho     = (int) substr($payload, $i + 2, 2);
        $campos[$id] = substr($payload, $i + 4, $tamanho);
        $i += 4 + $tamanho;
    }

    return $campos;
}

it('reproduz o vetor de teste do manual do BACEN, byte a byte', function () {
    $payload = StaticPixCode::make('123e4567-e12b-12d1-a456-426655440000', 'Fulano de Tal', 'BRASILIA')
        ->payload();

    expect($payload)->toBe(VETOR_BACEN);
})->group('support');

it('calcula o CRC sobre o literal 6304 também', function () {
    /*
     * o manual traz 1D3D para esse vetor. 4AAD é o que sai quando o cálculo
     * para antes do `6304` — o erro que gera um código que não lê.
     */
    expect(VETOR_BACEN)->toEndWith('63041D3D')
        ->and(VETOR_BACEN)->not->toEndWith('63044AAD');
})->group('support');

it('fecha cada campo com o tamanho que declara', function () {
    $campos = camposDe(VETOR_BACEN);

    expect($campos['00'])->toBe('01')
        ->and($campos['52'])->toBe('0000')
        ->and($campos['53'])->toBe('986')
        ->and($campos['58'])->toBe('BR')
        ->and($campos['59'])->toBe('Fulano de Tal')
        ->and($campos['60'])->toBe('BRASILIA')
        ->and($campos['63'])->toBe('1D3D')
        ->and(camposDe($campos['26']))->toBe([
            '00' => 'br.gov.bcb.pix',
            '01' => '123e4567-e12b-12d1-a456-426655440000',
        ])
        ->and(camposDe($campos['62']))->toBe(['05' => '***']);
})->group('support');

it('não leva campo de valor quando o valor não é informado', function () {
    $campos = camposDe(StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA')->payload());

    /* sem o 54, o pagador digita o valor — é para isso que serve o estático */
    expect($campos)->not->toHaveKey('54');
})->group('support');

it('escreve o valor em reais com ponto', function (Money|int|float $valor, string $esperado) {
    $campos = camposDe(
        StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA')->withAmount($valor)->payload()
    );

    expect($campos['54'])->toBe($esperado);
})->with([
    'Money em reais'     => [Money::reais('100,50'), '100.50'],
    'Money em centavos'  => [Money::centavos(10050), '100.50'],
    'número cru é reais' => [100, '100.00'],
    'float'              => [49.9, '49.90'],
])->group('support');

it('leva *** quando não há txid, e o txid quando há', function () {
    $semTxid = StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA');

    expect(camposDe(camposDe($semTxid->payload())['62']))->toBe(['05' => '***'])
        ->and(camposDe(camposDe($semTxid->withTxid('PEDIDO1234')->payload())['62']))
        ->toBe(['05' => 'PEDIDO1234']);
})->group('support');

it('é imutável: with() devolve outra instância', function () {
    $base = StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA');
    $com  = $base->withAmount(Money::reais('10,00'));

    expect($com)->not->toBe($base)
        ->and(camposDe($base->payload()))->not->toHaveKey('54')
        ->and(camposDe($com->payload())['54'])->toBe('10.00');
})->group('support');

it('conta o tamanho em bytes, não em caracteres', function () {
    /*
     * "Comércio" tem 8 caracteres e 9 bytes em UTF-8. Quem lê o payload anda
     * em bytes, então o tamanho declarado é em bytes — senão todos os campos
     * seguintes desalinham.
     */
    $campos = camposDe(StaticPixCode::make('fale@phpay.io', 'Comércio', 'BRASILIA')->payload());

    expect($campos['59'])->toBe('Comércio')
        ->and(strlen($campos['59']))->toBe(9)
        ->and($campos['60'])->toBe('BRASILIA');
})->group('support');

it('recusa o que o padrão não aceita', function (string $chave, string $nome, string $cidade, string $trecho) {
    expect(fn () => StaticPixCode::make($chave, $nome, $cidade))
        ->toThrow(ValidationException::class, $trecho);
})->with([
    'chave vazia'     => ['', 'Sixtec LTDA', 'BRASILIA', 'Informe a chave Pix'],
    'chave só espaço' => ['   ', 'Sixtec LTDA', 'BRASILIA', 'Informe a chave Pix'],
    'chave longa'     => [str_repeat('a', 78), 'Sixtec LTDA', 'BRASILIA', '77 caracteres'],
    'nome vazio'      => ['fale@phpay.io', '', 'BRASILIA', 'nome do recebedor'],
    'nome longo'      => ['fale@phpay.io', str_repeat('a', 26), 'BRASILIA', '25 caracteres'],
    'cidade vazia'    => ['fale@phpay.io', 'Sixtec LTDA', '', 'cidade do recebedor'],
    'cidade longa'    => ['fale@phpay.io', 'Sixtec LTDA', str_repeat('a', 16), '15 caracteres'],
])->group('support');

it('recusa txid fora do que o padrão aceita', function (string $txid) {
    $code = StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA');

    expect(fn () => $code->withTxid($txid))->toThrow(ValidationException::class, 'txid');
})->with([
    'com hífen'  => ['PEDIDO-1234'],
    'com espaço' => ['PEDIDO 1234'],
    'vazio'      => [''],
    'longo'      => [str_repeat('A', 26)],
])->group('support');

it('aceita o valor que o Money aceita, e recusa o que ele recusa', function () {
    $code = StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA');

    expect(fn () => $code->withAmount(10.555))->toThrow(ValidationException::class, 'duas casas decimais')
        ->and(fn () => $code->withAmount(-1))->toThrow(ValidationException::class, 'negativo');
})->group('support');
