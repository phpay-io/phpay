# Cobrança portável

`ChargeIntent` descreve uma cobrança **uma vez**, e cada gateway que declara a
capacidade sabe traduzir. É para a cobrança o que o [`Customer`](cliente.md) é
para o cliente e o [`Money`](valores-monetarios.md) é para o valor.

[← voltar à documentação](README.md)

```php
use PHPay\Support\{ChargeIntent, Customer, Money};

$intent = ChargeIntent::pix(
    amount: Money::reais('149,90'),
    customer: Customer::make(
        name: 'Maria Oliveira',
        document: '123.456.789-09',
        email: 'maria@exemplo.com',
    ),
);

$phpay->createCharge($intent);
```

O **mesmo** `$intent` nos três gateways da primeira fase produz três corpos
diferentes, cada um na unidade e no vocabulário daquele gateway:

| gateway | o que sai no corpo |
| --- | --- |
| Asaas | `"value": 149.9` — reais, porque o Asaas é de reais |
| Woovi | `"value": 14990` — centavos |
| Inter | `"valor": {"original": "149.90"}` — reais em **string**, que é o terceiro formato |
| PagBank | `items[].unit_amount` em centavos, e o total repetido em `qr_codes[0]` |
| Pagar.me | `items[].amount` em centavos, com o meio em `payments[0]` |
| AbacatePay | `products[].price` em centavos, com mínimo de 100 |

E o cliente, que tem seis grafias entre os gateways, sai certo em cada um:
`cpfCnpj` no Asaas, `taxID` na Woovi, `payer.identification.number` no Mercado
Pago, `devedor.cpf` no Inter.

## O que ela não resolve, e é de propósito

**O payload da cobrança diverge, e isso é a API de cada gateway, não uma
escolha da biblioteca.** O Asaas exige `billingType` e `dueDate`; a Woovi exige
`correlationID`; o Mercado Pago exige `payment_method_id` e
`transaction_amount`. `setCharge()` continua existindo e continua sendo o
caminho de quem quer o corpo cru daquele gateway — nada aqui o substitui.

O que a capacidade fecha é a **chamada**: quem integra escreve uma, e não dez.

## Três gateways não têm valor na cobrança

O PagBank e o Pagar.me somam `items`; a AbacatePay soma `products`. Neles não
existe "o valor da cobrança" — existe a conta das linhas. Por isso o
`ChargeIntent` tem as duas formas, e as duas funcionam nos dez:

```php
use PHPay\Support\Item;

$intent = ChargeIntent::comItens(Method::PIX, [
    new Item('Camiseta', Money::reais('54,90'), 2),
    new Item('Caneca', Money::reais('40,00')),
], $cliente);

$intent->amount->toCentavos();   // 14980 — a soma, e ela é autoritativa
```

| de | para | o que acontece |
| --- | --- | --- |
| valor escalar | gateway de itens | vira **uma linha só**, com o `description` por nome. A Efí já fazia isso no boleto desde antes |
| itens | gateway de valor escalar | sai o **total**. O detalhe das linhas se perde, porque a API deles não tem onde guardá-lo |

A segunda linha é limite da API, não descuido — e por isso está escrita aqui
em vez de acontecer calada. O valor nunca se perde: `amount` é a soma e é o
que vai.

### A AbacatePay recusa produto abaixo de R$ 1,00

```php
ChargeIntent::pix(Money::centavos(50));   // na AbacatePay: ValidationException
```

Ela exige 100 centavos por produto. A cobrança é **recusada antes de qualquer
HTTP**, nunca arredondada para cima — arredondar cobraria o valor errado, que é
o erro que o `Money` existe para impedir.

### O `externalId` da AbacatePay

É o id do produto **no seu sistema**, e ela o exige. Quando o `Item` traz
`reference`, é ele que vai. Quando não traz, o id é **derivado do nome** em vez
de sorteado, para o mesmo produto não aparecer com id diferente a cada
cobrança:

```php
new Item('Camiseta', Money::reais('54,90'), 2, reference: 'sku-123');
```

### A AbacatePay precisa de duas URLs

É a única dos dez cuja cobrança **é um link de checkout**, então ela precisa
saber para onde mandar o pagador de volta. As duas são da sua aplicação, não da
descrição da cobrança, então vêm por `withExtra()` — e a chamada recusa quando
faltam, em vez de inventar:

```php
$intent->withExtra([
    'returnUrl'     => 'https://loja.exemplo.com/volta',
    'completionUrl' => 'https://loja.exemplo.com/obrigado',
]);
```

## Pergunte o meio antes, ou deixe a chamada recusar

`SupportsCharges` responde *"este gateway cobra?"*. Nunca respondeu *"cobra
assim?"* — e a diferença importa: os dez declaram cobrança, e mesmo assim a
Rede só faz cartão de crédito e só quatro fazem boleto.

```php
use PHPay\Support\Method;

if (in_array(Method::PIX, $phpay->supportedMethods(), true)) {
    $phpay->createCharge($intent);
}
```

Sem perguntar, a chamada recusa com uma mensagem que nomeia o que o gateway
aceita — como a `NotImplementedException` já faz com capacidades.

> `supportedMethods()` descreve **o caminho portável**, não tudo o que o
> gateway vende. O Asaas também emite boleto e cartão por `setCharge()`; eles
> não aparecem aqui porque `createCharge()` ainda não sabe montá-los, e
> declarar um meio que a chamada recusaria é o mesmo erro que declarar uma
> capacidade e lançar de dentro dela.

## Vencimento

`dueDate` é `Y-m-d`, e é **opcional**: uma cobrança Pix sem vencimento é válida
— é a diferença entre `cob` e `cobv` na especificação do BACEN.

Onde o gateway expira por **duração** em vez de data, a chamada recusa em vez
de converter:

| gateway | vencimento |
| --- | --- |
| Asaas | **exige** `dueDate`, mesmo no Pix |
| Inter | opcional; com ele a cobrança vai para `cobv`, sem ele para `cob` |
| Woovi | expira por `expiresIn`, em segundos — **recusa** `dueDate` |
| Mercado Pago | expira por `date_of_expiration`, data e hora com fuso — **recusa** `dueDate` |

Converter uma data em duração exigiria assumir um fuso horário, e a biblioteca
não assume nenhum. Quem precisa disso informa o campo nativo:

```php
$intent->withExtra(['expiresIn' => 3600]);
```

## Referência

Quatro gateways querem o id da cobrança no **seu** sistema, sob quatro nomes:
`correlationID` na Woovi, `MerchantOrderId` na Cielo, `reference` na Rede,
`txid` no Inter. `reference` é o nome único:

```php
ChargeIntent::pix(Money::reais('149,90'), reference: 'pedido-42');
```

Sem ela, quem precisa gera uma com `Idempotency::key()`.

## O que é específico de um gateway

`withExtra()`, como no `Customer` — e com o mesmo aviso: um campo
**obrigatório** caindo ali é sinal de que falta algo ao value object, não
solução.

```php
$intent->withExtra(['soft_descriptor' => 'MINHA LOJA']);
```

## O Inter endereça por chave Pix

A chave é da **conta**, não da cobrança: a mesma responde por todas as
cobranças daquele `clientId`. Por isso ela vive na configuração do gateway:

```php
PHPay::make('inter', [
    'clientId'     => getenv('INTER_CLIENT_ID'),
    'clientSecret' => getenv('INTER_CLIENT_SECRET'),
    'certificate'  => Certificate::pair('/certificado.crt', '/chave.key'),
    'pixKey'       => getenv('INTER_PIX_KEY'),
]);
```

Quem atende mais de uma chave continua com `charge()->setKey()`.
