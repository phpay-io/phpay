<?php

/*
| Conferência de conformidade contra o sandbox do PagBank.
|
| Como o equivalente do Mercado Pago, este arquivo não é documentação: ele
| executa de verdade contra a API e relata o que cada operação devolveu, para
| validar que os payloads que a biblioteca monta são aceitos — algo que teste
| com HTTP mockado não consegue provar.
|
| Uso:
|
|   PAGBANK_TOKEN='seu-token-de-sandbox' php examples/pagbank/sandbox-check.php
|
| O token vem da variável de ambiente e nunca é impresso. O script sempre roda
| em sandbox: não há como apontá-lo para produção.
*/

use PHPay\Exceptions\{ApiException, PHPayException};
use PHPay\PagBank\Enums\IntervalUnitEnum;
use PHPay\PagBank\PagBankGateway;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

$token = getenv('PAGBANK_TOKEN');

if (!is_string($token) || $token === '') {
    fwrite(STDERR, "Defina PAGBANK_TOKEN com um token de sandbox.\n");
    fwrite(STDERR, "  PAGBANK_TOKEN='...' php examples/pagbank/sandbox-check.php\n");

    exit(1);
}

/* sandbox fixo: este script nunca toca produção */
$phpay = PHPay::gateway(new PagBankGateway($token, true));

$passou = 0;
$falhou = 0;

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

echo "Conferência de conformidade — PagBank (sandbox)\n\n";

$customer = [
    'name'   => 'PHPay Sandbox',
    'email'  => 'comprador@sandbox.pagseguro.com.br',
    'tax_id' => '12345678909',
];

echo "Pedidos (api.pagseguro.com)\n";

$pedido = checar('criar pedido com Pix', fn () => $phpay->charge()
    ->setCustomer($customer)
    ->addItem('PHPay sandbox check', 100)
    ->setQrCode(100)
    ->create());

if (is_array($pedido) && isset($pedido['id'])) {
    $pedidoId = (string) $pedido['id'];

    checar('buscar pedido por id', fn () => $phpay->charge()->find($pedidoId));

    $codigo = checar('código Pix copia-e-cola', fn () => $phpay->charge()->getPixCode($pedidoId));

    if ($codigo === null) {
        echo "         atenção: veio null — a conta de sandbox precisa de uma chave Pix ativa\n";
    }
}

echo "\nAssinaturas (api.assinaturas.pagseguro.com)\n";

$plano = checar('criar plano', fn () => $phpay->subscription()->createPlan([
    'name'        => 'PHPay sandbox check',
    'description' => 'Plano de verificação',
    'amount'      => ['value' => 100, 'currency' => 'BRL'],
    'interval'    => ['unit' => IntervalUnitEnum::MONTHS->value, 'length' => 1],
]));

if (is_array($plano) && isset($plano['id'])) {
    $planoId = (string) $plano['id'];

    checar('buscar plano por id', fn () => $phpay->subscription()->findPlan($planoId));

    $assinatura = checar('criar assinatura com assinante embutido', fn () => $phpay->subscription()
        ->setPlan($planoId)
        ->setCustomer($customer)
        ->create());

    if (is_array($assinatura) && isset($assinatura['id'])) {
        $assinaturaId = (string) $assinatura['id'];

        checar('buscar assinatura por id', fn () => $phpay->subscription()->find($assinaturaId));
        checar('cancelar assinatura', fn () => $phpay->subscription()->cancel($assinaturaId));
    }
}

checar('listar assinantes', fn () => $phpay->customer()->setFilter(['offset' => 0, 'limit' => 5])->getAll());

echo "\n";
echo "Resultado: {$passou} ok, {$falhou} falha(s).\n";

exit($falhou > 0 ? 1 : 0);
