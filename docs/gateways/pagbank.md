# PagBank

Duas particularidades, ambas resolvidas pela biblioteca.

**Duas APIs em hosts diferentes.** Pedidos vivem em `api.pagseguro.com`,
assinaturas em `api.assinaturas.pagseguro.com`. Cada recurso boota o client da
API certa — você não precisa saber disso.

**Pix não é uma cobrança.** Entra como `qr_codes` do pedido, e só um por pedido.
A conta precisa ter uma chave Pix ativa.

```php
use PHPay\PagBank\PagBankGateway;

$phpay = PHPay::gateway(new PagBankGateway(TOKEN_PAGBANK_SANDBOX))->charge();

$pedido = $phpay
    ->setCustomer(['name' => 'Mário', 'email' => 'fale@phpay.io', 'tax_id' => '12345678901'])
    ->addItem('Assinatura PHPay', 10050)   // R$ 100,50
    ->setQrCode(10050)
    ->setNotificationUrls(['https://exemplo.test/webhook/pagbank'])
    ->create();

$phpay->getPixCode($pedido['id']);   // de qr_codes[0].text
```

Cartão e boleto, aí sim, vão em `charges`:

```php
$phpay
    ->setCustomer($cliente)
    ->addItem('Camiseta', 5990, 2)
    ->setCharges([[
        'reference_id'   => 'cobranca-1',
        'amount'         => ['value' => 11980, 'currency' => 'BRL'],
        'payment_method' => ['type' => 'CREDIT_CARD', 'installments' => 1, 'capture' => true],
    ]])
    ->create();
```

Assinaturas sempre pertencem a um plano, e o assinante pode nascer junto:

```php
$phpay = PHPay::gateway(new PagBankGateway(TOKEN_PAGBANK_SANDBOX))->subscription();

$plano = $phpay->createPlan([
    'name'     => 'Plano PHPay Mensal',
    'amount'   => ['value' => 4990, 'currency' => 'BRL'],   // R$ 49,90
    'interval' => ['unit' => 'MONTHS', 'length' => 1],
]);

$phpay->setPlan($plano['id'])
    ->setCustomer(['name' => 'Mário', 'email' => 'fale@phpay.io', 'tax_id' => '12345678901'])
    ->create();
```
