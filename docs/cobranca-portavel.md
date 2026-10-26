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
