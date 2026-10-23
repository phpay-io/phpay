# AbacatePay

Pix nativo, e **mesmo assim não declara `SupportsPixKeys`** — aquela capacidade
é sobre gerenciar chaves e QR Code estático, coisa de PSP. Aqui Pix é o método
de pagamento da cobrança, e o único aceito.

Também não declara assinaturas: a API documenta `ONE_TIME` como a única
frequência aceita.

A cobrança é um **link de pagamento montado a partir de produtos**, não de um
valor solto — o total vem calculado em `amount`. Preço em centavos, com
**mínimo de 100** (R$ 1,00) por produto.

```php
use PHPay\AbacatePay\AbacatePayGateway;

$gateway = new AbacatePayGateway(ABACATEPAY_TOKEN);

$cobranca = PHPay::gateway($gateway)->charge()
    ->setCustomer([
        'name'      => 'Mário Lucas',
        'email'     => 'fale@phpay.io',
        'cellphone' => '(11) 4002-8922',
        'taxId'     => '12345678901',
    ])
    ->addProduct('prod-1234', 'Assinatura PHPay', 2000)   // R$ 20,00
    ->setUrls(
        completionUrl: 'https://exemplo.test/obrigado',
        returnUrl: 'https://exemplo.test/loja'
    )
    ->create();

$phpay->getPaymentUrl($cobranca);   // o link para onde mandar o cliente
$phpay->isDevMode($cobranca);       // em qual ambiente a cobrança nasceu
```

O `externalId` do produto é o id **no seu sistema** — o AbacatePay cria o
produto do lado dele a partir dele, então precisa ser único.

## Cupons de desconto

Nenhum outro gateway da biblioteca tem isso, então não é capacidade: vive no
gateway concreto, como o `webhookDeliveries()` do Pagar.me.

```php
$gateway->coupons()->create([
    'code'         => 'PHPAY10',
    'discountKind' => 'PERCENTAGE',
    'discount'     => 10,
]);
```

## Estorno

A AbacatePay **não declara a capacidade de estorno**. Os endpoints de reembolso
que a documentação dela descreve são da **v2**, para checkout, link de pagamento
e checkout transparente — outra família de recursos. A biblioteca fala com a
**v1** (`billing/create`), e não há reembolso documentado para o `billing`.

Enquanto isso não for confirmado, `$phpay->refund($id)` levanta
`NotImplementedException`, em vez de chamar um endpoint adivinhado.
