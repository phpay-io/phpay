<?php

use PHPay\AbacatePay\Resources\Coupon\Coupon;

it('cria o cupom no endpoint de coupon', function () {
    $history = [];
    $client  = abacateClient([jsonResponse(['data' => ['code' => 'PHPAY10']])], $history);

    (new Coupon('token', $client))->create([
        'code'         => 'PHPAY10',
        'discountKind' => 'PERCENTAGE',
        'discount'     => 10,
    ]);

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/coupon/create')
        ->and(recordedBody($history, 0))->toBe([
            'code'         => 'PHPAY10',
            'discountKind' => 'PERCENTAGE',
            'discount'     => 10,
        ]);
})->group('abacatepay');

it('lista cupons com e sem filtro', function () {
    $history = [];
    $client  = abacateClient([jsonResponse(['data' => []]), jsonResponse(['data' => []])], $history);

    $coupon = new Coupon('token', $client);

    $coupon->getAll();
    $coupon->setQueryParams(['limit' => 5])->getAll();

    expect((string) $history[0]['request']->getUri())->toEndWith('/coupon/list')
        ->and((string) $history[1]['request']->getUri())->toContain('limit=5');
})->group('abacatepay');
