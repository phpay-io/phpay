<?php

use PHPay\Efi\Resources\Pix\Pix;
use PHPay\PHPay;

it('cria chave aleatória sem corpo na requisição', function () {
    $history = [];
    $pix     = PHPay::gateway(efiPixGateway([
        jsonResponse(['chave' => '345e4568-e89b-12d3-a456-006655440001']),
    ], $history))->pix();

    expect($pix)->toBeInstanceOf(Pix::class)
        ->and($pix->createKey()['chave'])->toBe('345e4568-e89b-12d3-a456-006655440001')
        ->and($history[1]['request']->getMethod())->toBe('POST')
        ->and((string) $history[1]['request']->getUri())->toBe('https://pix-h.api.efipay.com.br/v2/gn/evp')
        ->and((string) $history[1]['request']->getBody())->toBe('');
})->group('efi');

it('lista e remove chaves aleatórias', function () {
    $history = [];
    $pix     = efiPixGateway([
        jsonResponse(['chaves' => ['345e4568-e89b-12d3-a456-006655440001']]),
        new GuzzleHttp\Psr7\Response(204),
    ], $history)->pix();

    expect($pix->getAll()['chaves'])->toHaveCount(1)
        ->and($pix->destroy('345e4568-e89b-12d3-a456-006655440001'))->toBeTrue()
        ->and($history[2]['request']->getMethod())->toBe('DELETE')
        ->and((string) $history[2]['request']->getUri())->toEndWith('v2/gn/evp/345e4568-e89b-12d3-a456-006655440001');
})->group('efi');
