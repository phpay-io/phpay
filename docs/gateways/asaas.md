# Asaas

Um dos dois com as cinco capacidades, ao lado do Woovi. É PSP, então emite
chave Pix própria e gerencia webhooks por API.

```php
use PHPay\Asaas\AsaasGateway;

$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));
```

## Cobranças

```php
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX))->charge();

/* cria a cobrança, criando também o cliente */
$phpay->setCharge($cobranca)->setCustomer($cliente)->create();

/* reaproveita um cliente que já existe — evita cadastro duplicado */
$phpay->setCharge($cobranca)->setCustomerId('cus_000006337812')->create();

$phpay->find($id);
$phpay->getAll();
$phpay->setQueryParams(['limit' => 2])->getAll();
$phpay->update($id, $dados);
$phpay->destroy($id);
$phpay->restore($id);

$phpay->getStatus($id);
$phpay->getDigitableLine($id);
$phpay->getQrCodePix($id);

$phpay->confirmReceipt($id, [
    'paymentDate'    => date('Y-m-d'),
    'value'          => 100.00,
    'notifyCustomer' => true,
]);
$phpay->undoConfirmReceipt($id);
```

## Clientes

```php
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

$cliente = $phpay->customer(['name' => 'Mário Lucas', 'cpfCnpj' => '12345678901'])->create();

$phpay->customer()->find($cliente['id']);
$phpay->customer()->setFilter(['cpfCnpj' => '12345678901'])->getAll();
$phpay->customer(['name' => 'Novo Nome'])->update($cliente['id']);
$phpay->customer()->getNotifications($cliente['id']);
$phpay->customer()->destroy($cliente['id']);
$phpay->customer()->restore($cliente['id']);
```

## Assinaturas

A assinatura gera uma cobrança por ciclo, e cada uma é uma cobrança comum:
aparece em `getPayments()` e é tratada pelo recurso de cobrança.

```php
use PHPay\Asaas\Enums\SubscriptionCycleEnum;

$assinaturas = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX))->subscription();

$assinatura = $assinaturas
    ->setCustomer($cliente)                          // ou setCustomerId('cus_...')
    ->setAmount(Money::reais('49,90'))
    ->setCycle(SubscriptionCycleEnum::MONTHLY)
    ->setSubscription([
        'billingType' => 'BOLETO',
        'nextDueDate' => '2026-10-10',
        'description' => 'Plano mensal',
    ])
    ->create();
```

> O `create([...])` com o payload inteiro continua funcionando, e o array
> sobrescreve o que os setters montaram. O `cycle` é obrigatório: sem ele o
> Asaas recusa, e o PHPay barra antes.

Consulta e ciclo de vida:

```php
$assinaturas->setQueryParams(['customer' => 'cus_...', 'status' => 'ACTIVE'])->getAll();
$assinaturas->find($id);

/* muda as próximas cobranças; com updatePendingPayments, as pendentes também */
$assinaturas->update($id, ['description' => 'Plano anual', 'updatePendingPayments' => true]);

/* pausa: para de gerar cobranças e mantém as que existem */
$assinaturas->deactivate($id);
$assinaturas->reactivate($id, '2026-11-10');   // o Asaas exige um novo vencimento

/* remove: as cobranças pendentes e vencidas vão junto; as pagas ficam */
$assinaturas->destroy($id);
```

Cobranças, carnê e cartão:

```php
$assinaturas->getPayments($id, ['status' => 'PENDING']);

/* o carnê vem como os bytes do PDF */
file_put_contents('carne.pdf', $assinaturas->paymentBook($id, month: 12, year: 2026));

/* troca o cartão sem cobrar — as cobranças pendentes passam para o novo */
$assinaturas->updateCreditCard($id, [
    'creditCardToken' => $token,        // ou creditCard + creditCardHolderInfo
    'remoteIp'        => $ipDoComprador,
]);
```

Nota fiscal emitida automaticamente para cada cobrança:

```php
$assinaturas->createInvoiceSettings($id, [
    'municipalServiceName' => 'Desenvolvimento de software',
    'effectiveDatePeriod'  => 'ON_PAYMENT_CONFIRMATION',
    'taxes'                => [   // os sete são obrigatórios; 0 quando não houver
        'retainIss' => false,
        'iss'       => 2,
        'pis'       => 0.65,
        'cofins'    => 3,
        'csll'      => 0,
        'inss'      => 0,
        'ir'        => 0,
    ],
]);

$assinaturas->getInvoiceSettings($id);
$assinaturas->updateInvoiceSettings($id, [...]);
$assinaturas->destroyInvoiceSettings($id);
$assinaturas->getInvoices($id);   // as notas já emitidas
```

## Webhooks e chaves Pix

```php
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

/* webhooks com CRUD completo — exclusividade do Asaas */
$phpay->webhook(WEBHOOK)->create();
$phpay->webhook()->getAll();
$phpay->webhook()->update($id, $dados);
$phpay->webhook()->destroy($id);

/* chaves Pix e QR Code estático */
$chave = $phpay->pix()->createKey();
$phpay->pix()->getAll();
$phpay->pix()->staticQrCode(['addressKey' => $chave['key'], 'value' => 25.00]);
$phpay->pix()->destroy($chave['id']);
```
