# Rede

Adquirente, e a forma mais estreita da biblioteca: **só
cobranças**. Não há recurso de cliente nem assinatura gerenciável — a
transação tem um campo `subscription`, mas é uma flag para a adquirente, não
algo que você liste ou cancele.

A particularidade é a autenticação: **OAuth2 `client_credentials` num host
separado do de API**, com o caminho do token diferente em cada ambiente. E o
token **expira** — o PHPay renegocia sozinho quando isso acontece.

```php
use PHPay\Rede\Enums\TransactionKindEnum;
use PHPay\Rede\RedeGateway;

/* nenhuma chamada de rede aqui: o token é negociado no primeiro uso */
$gateway = new RedeGateway(REDE_PV, REDE_TOKEN);

$transacao = PHPay::gateway($gateway)->charge()
    ->setReference('pedido-1')
    ->setCard('5448280000000007', 'MARIO LUCAS', '12', '2030', '123')
    ->setPayment(2099, TransactionKindEnum::CREDIT)   // R$ 20,99
    ->setSoftDescriptor('PHPAY')
    ->create();
```

Fluxo em duas etapas — autoriza agora, captura quando o pedido for separado:

```php
$phpay->setPayment(5000, capture: false)->create();

$phpay->capture($tid);
$phpay->refund($tid, 1000);   // estorna R$ 10,00
```

O código de retorno `"00"` significa aprovada:

```php
use PHPay\Rede\Enums\TransactionStatusEnum;

TransactionStatusEnum::approved($phpay->getStatus($tid));
```

Num processo longo, dá para inspecionar ou descartar o token em mãos:

```php
$gateway->authorization()->hasValidToken();
$gateway->authorization()->forget();
```
