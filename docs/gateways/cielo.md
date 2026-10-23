# Cielo

A primeira **adquirente** da biblioteca, e a forma mostra: não há recurso de
cliente — ele é um campo da venda. Daí as duas capacidades.

A particularidade é que a Cielo separa **dois hosts por tipo de operação**:
escritas vão para `api.cieloecommerce...`, consultas para
`apiquery.cieloecommerce...`. O mesmo recurso usa os dois, e o PHPay roteia
sozinho — `create()` vai num, `find()` no outro.

```php
use PHPay\Cielo\CieloGateway;

$phpay = PHPay::gateway(new CieloGateway(MERCHANT_ID, MERCHANT_KEY))->charge();

$venda = $phpay
    ->setOrderId('pedido-1')
    ->setCustomer(['Name' => 'Mário Lucas'])
    ->setPix(15700)             // R$ 157,00
    ->setRequestId('pedido-1')  // idempotência
    ->create();

$phpay->getPixCode($venda['Payment']['PaymentId']);
```

Cartão em duas etapas — autoriza agora, captura depois:

```php
$phpay
    ->setCustomer(['Name' => 'Mário Lucas'])
    ->setCreditCard(15700, $cartao, installments: 3)   // capture: false por padrão
    ->create();

$phpay->capture($paymentId);
$phpay->cancel($paymentId, 2500);   // estorna R$ 25,00
$phpay->refund($paymentId, 2500);   // o mesmo, pelo nome da capacidade
```

`void` é uma rota só: ela anula uma autorização e estorna uma venda capturada, e
o que acontece depende do estado em que a venda estava. `cancel()` continua
existindo porque é o nome honesto da primeira metade; `refund()` é o nome que
[a capacidade](../capacidades.md) declara, e é por ele que a facade chega aqui.

## Recorrência

A Cielo **não tem endpoint de criar assinatura**: a recorrência nasce de uma
venda com um bloco `RecurrentPayment`, e só então ganha um `RecurrentPaymentId`
próprio. Sempre cobra cartão.

```php
use PHPay\Cielo\Enums\RecurrentIntervalEnum;

$phpay = PHPay::gateway(new CieloGateway(MERCHANT_ID, MERCHANT_KEY))->subscription();

$recorrencia = $phpay
    ->setCustomer(['Name' => 'Mário Lucas'])
    ->setCard($cartao)
    ->setInterval(RecurrentIntervalEnum::MONTHLY)
    ->setEndDate('2027-12-31')
    ->create(15700);

$id = $recorrencia['Payment']['RecurrentPayment']['RecurrentPaymentId'];

$phpay->updateAmount($id, 19900);
$phpay->deactivate($id);
$phpay->reactivate($id);
```
