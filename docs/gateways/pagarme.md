# Pagar.me

Autenticação Basic com a secret key, ambiente pelo prefixo da chave, e Pix como
forma de pagamento do pedido.

```php
use PHPay\PagarMe\PagarMeGateway;

$gateway = new PagarMeGateway(SECRET_KEY_PAGARME);

$pedido = PHPay::gateway($gateway)->charge()
    ->setCustomer([
        'name'     => 'Mário Lucas',
        'email'    => 'fale@phpay.io',
        'document' => '12345678901',
    ])
    ->addItem('Assinatura PHPay', 10050)   // R$ 100,50
    ->setPix(1800)                          // expira em 30 minutos
    ->create();

$phpay->getPixCode($pedido['id']);   // de charges[0].last_transaction.qr_code
```

O cancelamento é `DELETE`, com valor opcional para estorno parcial:

```php
$phpay->cancel($cobrancaId, 2500);   // estorna R$ 25,00
$phpay->cancel($cobrancaId);         // estorna tudo
```

Assinaturas aceitam um plano ou a recorrência no próprio payload:

```php
$phpay = PHPay::gateway($gateway)->subscription();

$plano = $phpay->createPlan([
    'name'           => 'Plano PHPay Mensal',
    'interval'       => 'month',
    'interval_count' => 1,
    'items'          => [[
        'name'           => 'Mensalidade',
        'quantity'       => 1,
        'pricing_scheme' => ['price' => 4990],   // R$ 49,90
    ]],
]);

$phpay->setPlan($plano['id'])
    ->setCustomerId($clienteId)
    ->create(['payment_method' => 'pix']);
```

## Consultando entregas de webhook

O Pagar.me deixa ler e reenviar os eventos que já despachou. Isso **não** é a
capacidade `SupportsWebhooks` — o cadastro dos endpoints é no dashboard — então
vive no gateway concreto, não na facade:

```php
$gateway->webhookDeliveries()->setFilter(['size' => 10])->getAll();
$gateway->webhookDeliveries()->resend($hookId);
```

É assim que o modelo de capacidades abre espaço para o que só um gateway
oferece: quem segura `PagarMeGateway` alcança, quem tipa uma capacidade não.
