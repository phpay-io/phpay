# Mercado Pago

Ambiente pelo prefixo do token. `POST /v1/payments` exige o header
`X-Idempotency-Key`: o PHPay gera uma chave por chamada, e
`setIdempotencyKey()` deixa você fixar a sua — assim um retry da mesma operação
de negócio não cria duas cobranças.

```php
use PHPay\MercadoPago\Enums\PaymentMethodEnum;
use PHPay\MercadoPago\MercadoPagoGateway;

$gateway = new MercadoPagoGateway(ACCESS_TOKEN);

$cobranca = PHPay::gateway($gateway)->charge()
    ->setCharge([
        'transaction_amount' => 100.50,
        'payment_method_id'  => PaymentMethodEnum::PIX->value,
        'notification_url'   => 'https://exemplo.test/webhook/mercadopago',
    ])
    ->setPayer(['email' => 'comprador@exemplo.test'])
    ->setIdempotencyKey('pedido-123456')
    ->create();

$phpay->getPixCode($cobranca['id']);
```

Assinaturas usam `/preapproval`, com ou sem plano associado:

```php
$phpay = PHPay::gateway($gateway)->subscription();

$phpay->setPayerEmail('comprador@exemplo.test')->create([
    'reason'         => 'Assinatura PHPay',
    'back_url'       => 'https://exemplo.test/retorno',
    'auto_recurring' => [
        'frequency'          => 1,
        'frequency_type'     => 'months',
        'transaction_amount' => 100.50,
        'currency_id'        => 'BRL',
    ],
]);

/* com plano, a recorrência vem do plano */
$phpay->setPayerEmail('comprador@exemplo.test')
    ->setPlan('2c938084726fca480172750000000000')
    ->create(['back_url' => 'https://exemplo.test/retorno']);
```

> O recurso `Customer` do Mercado Pago existe para cartões salvos — **não** é
> pré-requisito para cobrar, já que o pagamento carrega `payer.email` direto. A
> API também não oferece exclusão de cliente.
