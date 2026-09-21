<?php

use PHPay\Efi\Enums\{AccountTypeEnum, PeriodicityEnum};
use PHPay\Efi\Resources\Subscription\Subscription;
use PHPay\Exceptions\ValidationException;
use PHPay\PHPay;
use PHPay\Support\{Customer, Money};

/**
 * Pix Automático resource on a mocked client.
 *
 * @param array<int, mixed> $responses
 * @param array<int, mixed> $history
 * @return Subscription
 */
function pixAutomatico(array $responses, array &$history = []): Subscription
{
    return new Subscription(['access_token' => 'tok', 'token_type' => 'Bearer'], null, true, efiPixClient($responses, $history));
}

/**
 * a recurrence with every required field.
 *
 * @param Subscription $subscription
 * @return Subscription
 */
function recorrenciaValida(Subscription $subscription): Subscription
{
    $subscription
        ->setCustomer(new Customer('Mário Lucas', '12345678909'))
        ->setContract('CONTRATO-2026-001')
        ->setDescription('Plano mensal')
        ->setAmount(Money::reais('49,90'))
        ->setPeriodicity(PeriodicityEnum::MONTHLY, '2026-10-01');

    return $subscription;
}

it('cria a recorrência pela facade no formato do BACEN', function () {
    $history = [];

    recorrenciaValida(PHPay::gateway(efiPixGateway([jsonResponse(['idRec' => 'RR123'])], $history))->subscription())
        ->setLocation(42)
        ->create();

    expect($history[1]['request']->getMethod())->toBe('POST')
        ->and((string) $history[1]['request']->getUri())->toBe('https://pix-h.api.efipay.com.br/v2/rec')
        ->and(recordedBody($history, 1))->toBe([
            'vinculo' => [
                'devedor'  => ['nome' => 'Mário Lucas', 'cpf' => '12345678909'],
                'contrato' => 'CONTRATO-2026-001',
                'objeto'   => 'Plano mensal',
            ],
            'valor'               => ['valorRec' => '49.90'],
            'calendario'          => ['dataInicial' => '2026-10-01', 'periodicidade' => 'MENSAL'],
            'loc'                 => 42,
            'politicaRetentativa' => 'NAO_PERMITE',
        ]);
})->group('efi');

it('aceita valor variável com mínimo, data final, retentativas e ativação por cobrança', function () {
    $history = [];

    pixAutomatico([jsonResponse(['idRec' => 'RR123'])], $history)
        ->setCustomer(['nome' => 'Sixtec LTDA', 'cnpj' => '12345678000199'])
        ->setContract('C-1')
        ->setMinimumAmount(Money::reais(30))
        ->setPeriodicity(PeriodicityEnum::YEARLY, '2026-10-01', '2030-10-01')
        ->allowRetries()
        ->setActivationTxid('33beb661beda44a8928fef47dbeb2dc5')
        ->create();

    $body = recordedBody($history);

    expect($body['valor'])->toBe(['valorMinimoRecebedor' => '30.00'])
        ->and($body['calendario'])->toBe(['dataInicial' => '2026-10-01', 'dataFinal' => '2030-10-01', 'periodicidade' => 'ANUAL'])
        ->and($body['politicaRetentativa'])->toBe('PERMITE_3R_7D')
        ->and($body['ativacao'])->toBe(['dadosJornada' => ['txid' => '33beb661beda44a8928fef47dbeb2dc5']]);
})->group('efi');

it('recusa recorrência incompleta sem chamar a API', function (Closure $monta, string $message) {
    $history = [];

    expect(fn () => $monta(pixAutomatico([], $history))->create())
        ->toThrow(ValidationException::class, $message);

    expect($history)->toBeEmpty();
})->with([
    'sem contrato'   => [fn (Subscription $s) => $s->setCustomer(new Customer('Mário', '12345678909')), 'setContract'],
    'sem devedor'    => [fn (Subscription $s) => $s->setContract('C-1'), 'exige devedor'],
    'sem calendário' => [fn (Subscription $s) => $s->setContract('C-1')->setCustomer(new Customer('Mário', '12345678909')), 'setPeriodicity'],
    'sem valor'      => [fn (Subscription $s) => $s->setContract('C-1')
        ->setCustomer(new Customer('Mário', '12345678909'))
        ->setPeriodicity(PeriodicityEnum::MONTHLY, '2026-10-01'), 'setMinimumAmount'],
    'contrato longo' => [fn (Subscription $s) => recorrenciaValida($s)->setContract(str_repeat('x', 36)), 'até 35'],
])->group('efi');

it('conta caracteres, não bytes, no limite de 35', function () {
    $history = [];

    /* 35 caracteres com acento passam de 35 bytes */
    recorrenciaValida(pixAutomatico([jsonResponse([])], $history))
        ->setDescription(str_repeat('ç', 35))
        ->create();

    expect($history)->toHaveCount(1);
})->group('efi');

it('cancela a recorrência pelo status do BACEN', function () {
    $history = [];

    pixAutomatico([jsonResponse(['status' => 'CANCELADA'])], $history)->cancel('RR123');

    expect($history[0]['request']->getMethod())->toBe('PATCH')
        ->and((string) $history[0]['request']->getUri())->toEndWith('v2/rec/RR123')
        ->and(recordedBody($history))->toBe(['status' => 'CANCELADA']);
})->group('efi');

it('cria o location da jornada de QR Code sem corpo', function () {
    $history = [];

    expect(pixAutomatico([jsonResponse(['id' => 42])], $history)->createLocation()['id'])->toBe(42)
        ->and((string) $history[0]['request']->getUri())->toEndWith('v2/locrec')
        ->and((string) $history[0]['request']->getBody())->toBe('');
})->group('efi');

it('cria a cobrança do ciclo com a conta recebedora', function () {
    $history = [];

    pixAutomatico([jsonResponse(['txid' => 'abc'])], $history)
        ->setReceiver('12345-6', AccountTypeEnum::CHECKING, '0001')
        ->createCharge('RR123', Money::reais('49,90'), '2026-11-05', ['infoAdicional' => 'Plano mensal']);

    expect((string) $history[0]['request']->getUri())->toEndWith('v2/cobr')
        ->and(recordedBody($history))->toBe([
            'idRec'         => 'RR123',
            'calendario'    => ['dataDeVencimento' => '2026-11-05'],
            'valor'         => ['original' => '49.90'],
            'ajusteDiaUtil' => true,
            'recebedor'     => ['agencia' => '0001', 'conta' => '12345-6', 'tipoConta' => 'CORRENTE'],
            'infoAdicional' => 'Plano mensal',
        ]);
})->group('efi');

it('exige a conta recebedora na cobrança do ciclo', function () {
    $history = [];

    expect(fn () => pixAutomatico([], $history)->createCharge('RR123', Money::reais(10), '2026-11-05'))
        ->toThrow(ValidationException::class, 'setReceiver');

    expect($history)->toBeEmpty();
})->group('efi');

it('consulta e cancela a cobrança do ciclo', function () {
    $history      = [];
    $subscription = pixAutomatico([jsonResponse(['status' => 'ATIVA']), jsonResponse(['status' => 'CANCELADA'])], $history);

    $subscription->findCharge('txid123');
    $subscription->cancelCharge('txid123');

    expect((string) $history[0]['request']->getUri())->toEndWith('v2/cobr/txid123')
        ->and($history[1]['request']->getMethod())->toBe('PATCH')
        ->and(recordedBody($history, 1))->toBe(['status' => 'CANCELADA']);
})->group('efi');
