<?php

/*
| Conferência de conformidade contra o sandbox do Mercado Pago.
|
| Diferente dos outros arquivos de examples/, este não é documentação: ele
| executa de verdade contra a API e relata o que cada operação devolveu. Serve
| para validar, antes de um release, que os payloads que a biblioteca monta são
| aceitos pelo gateway — algo que teste com HTTP mockado não consegue provar.
|
| Uso:
|
|   MP_ACCESS_TOKEN='TEST-...' php examples/mercadopago/sandbox-check.php
|
| O token vem da variável de ambiente e nunca é impresso. O script recusa
| qualquer credencial que não seja de teste, para não tocar em produção por
| acidente.
*/

use PHPay\Exceptions\{ApiException, PHPayException};
use PHPay\MercadoPago\Enums\{FrequencyTypeEnum, PaymentMethodEnum};
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

$token = getenv('MP_ACCESS_TOKEN');

if (!is_string($token) || $token === '') {
    fwrite(STDERR, "Defina MP_ACCESS_TOKEN com um access token de teste.\n");
    fwrite(STDERR, "  MP_ACCESS_TOKEN='TEST-...' php examples/mercadopago/sandbox-check.php\n");

    exit(1);
}

$gateway = new MercadoPagoGateway($token);

if (!$gateway->isSandbox()) {
    fwrite(STDERR, "O token informado não é de teste (não começa com TEST-).\n");
    fwrite(STDERR, "Este script recusa credenciais de produção.\n");

    exit(1);
}

$phpay = PHPay::gateway($gateway);

$passou  = 0;
$falhou  = 0;
$context = [];

/**
 * roda uma operação e relata o resultado.
 *
 * @param string $titulo
 * @param callable(): mixed $operacao
 * @return mixed|null
 */
function checar(string $titulo, callable $operacao): mixed
{
    global $passou, $falhou;

    try {
        $resultado = $operacao();

        $passou++;
        echo "  ok   {$titulo}\n";

        return $resultado;
    } catch (ApiException $e) {
        $falhou++;
        echo "  FALHA  {$titulo}\n";
        echo "         HTTP {$e->getStatusCode()} — {$e->getMessage()}\n";

        if (!empty($e->getResponse())) {
            echo '         corpo: ' . json_encode($e->getResponse(), JSON_UNESCAPED_UNICODE) . "\n";
        }

        return null;
    } catch (PHPayException $e) {
        $falhou++;
        echo "  FALHA  {$titulo}\n";
        echo "         {$e->getMessage()}\n";

        return null;
    }
}

echo "Conferência de conformidade — Mercado Pago (sandbox)\n\n";

echo "Cobranças\n";

$charge = checar('criar cobrança Pix', function () use ($phpay) {
    return $phpay->charge()
        ->setCharge([
            'transaction_amount' => 1.00,
            'payment_method_id'  => PaymentMethodEnum::PIX->value,
            'description'        => 'PHPay sandbox check',
            'external_reference' => 'phpay-' . bin2hex(random_bytes(4)),
        ])
        ->setPayer(['email' => 'test_user_' . random_int(1000, 9999) . '@testuser.com'])
        ->create();
});

if (is_array($charge) && isset($charge['id'])) {
    $chargeId = (string) $charge['id'];

    checar('buscar cobrança por id', fn () => $phpay->charge()->find($chargeId));
    checar('status da cobrança', fn () => $phpay->charge()->getStatus($chargeId));

    $codigo = checar('código Pix copia-e-cola', fn () => $phpay->charge()->getPixCode($chargeId));

    if ($codigo === null) {
        echo "         atenção: veio null — confira o caminho point_of_interaction.transaction_data.qr_code\n";
    }

    checar('cancelar cobrança', fn () => $phpay->charge()->cancel($chargeId));
}

checar('buscar cobranças com filtro', fn () => $phpay->charge()
    ->setQueryParams(['limit' => 5])
    ->getAll());

echo "\nClientes\n";

$email = 'test_user_' . random_int(1000, 9999) . '@testuser.com';

$customer = checar('criar cliente', fn () => $phpay->customer([
    'email'      => $email,
    'first_name' => 'PHPay',
    'last_name'  => 'Sandbox',
])->create());

if (is_array($customer) && isset($customer['id'])) {
    $customerId = (string) $customer['id'];

    checar('buscar cliente por id', fn () => $phpay->customer()->find($customerId));
    checar('localizar cliente por e-mail', fn () => $phpay->customer()->findByEmail($email));
}

echo "\nAssinaturas\n";

/*
| Sem card_token_id a assinatura nasce em "pending" — é o suficiente para provar
| que o payload é aceito, que é o que este script verifica.
*/
$subscription = checar('criar assinatura sem plano', fn () => $phpay->subscription()
    ->setPayerEmail('test_user_' . random_int(1000, 9999) . '@testuser.com')
    ->create([
        'reason'         => 'PHPay sandbox check',
        'back_url'       => 'https://phpay.io/retorno',
        'auto_recurring' => [
            'frequency'          => 1,
            'frequency_type'     => FrequencyTypeEnum::MONTHS->value,
            'transaction_amount' => 1.00,
            'currency_id'        => 'BRL',
        ],
    ]));

if (is_array($subscription) && isset($subscription['id'])) {
    $subscriptionId = (string) $subscription['id'];

    checar('buscar assinatura por id', fn () => $phpay->subscription()->find($subscriptionId));
    checar('cancelar assinatura', fn () => $phpay->subscription()->cancel($subscriptionId));
}

echo "\n";
echo "Resultado: {$passou} ok, {$falhou} falha(s).\n";

exit($falhou > 0 ? 1 : 0);
