![capa-redes](https://github.com/user-attachments/assets/20051d3a-ecbf-4d01-8c29-50c43b8d3af4)

<p align="center">
    <a href="https://github.com/phpay-io/phpay/releases"><img src="https://poser.pugx.org/phpay-io/phpay/v/stable" alt="Versão estável"></a>
    <a href="https://www.php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.1-brightgreen.svg" alt="Versão do PHP"></a>
    <a href="https://packagist.org/packages/phpay-io/phpay"><img src="https://poser.pugx.org/phpay-io/phpay/downloads" alt="Downloads"></a>
    <a href="https://github.com/phpay-io/phpay/actions/workflows/tests.yml"><img src="https://github.com/phpay-io/phpay/actions/workflows/tests.yml/badge.svg?branch=develop" alt="Testes"></a>
    <a href="./LICENSE.md"><img src="https://img.shields.io/badge/licen%C3%A7a-BUSL--1.1-blue.svg" alt="Licença"></a>
</p>

<p align="center">
    Uma interface só para os gateways de pagamento brasileiros.
</p>

---

## Sumário

- [Por que o PHPay](#por-que-o-phpay)
- [Gateways suportados](#gateways-suportados)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Conceitos](#conceitos)
  - [Capacidades](#capacidades)
  - [Tratamento de erros](#tratamento-de-erros)
  - [Ambientes e credenciais](#ambientes-e-credenciais)
  - [Unidade monetária](#unidade-monetária)
- [Gateways](#gateways)
  - [Asaas](#asaas)
  - [Mercado Pago](#mercado-pago)
  - [PagBank](#pagbank)
  - [Pagar.me](#pagarme)
  - [Cielo](#cielo)
  - [Rede](#rede)
  - [AbacatePay](#abacatepay)
  - [Woovi/OpenPix](#wooviopenpix)
  - [Efí](#efí)
- [Exemplos executáveis](#exemplos-executáveis)
- [Migrando da v1](#migrando-da-v1)
- [Roadmap](#roadmap)
- [Contribuindo](#contribuindo)
- [Segurança](#segurança)
- [Licença](#licença)

---

## Por que o PHPay

Cada gateway brasileiro resolve os mesmos problemas de um jeito diferente: um
chama de `payment`, outro de `order`, outro de `charge`. Um quer reais, outro
quer centavos. Um separa ambiente por URL, outro pelo prefixo do token.

O PHPay normaliza isso numa interface só, **sem esconder o que é genuinamente
diferente**. Quando um gateway não oferece um recurso, ele não finge que
oferece — ele declara o que sabe fazer, e você descobre em tempo de análise
estática, não em produção.

```php
use PHPay\Asaas\AsaasGateway;
use PHPay\PHPay;

$phpay = PHPay::gateway(new AsaasGateway(TOKEN));

$phpay->charge()->setCharge($cobranca)->setCustomerId($clienteId)->create();
```

Trocar de gateway é trocar a linha do construtor.

---

## Gateways suportados

| Gateway | Clientes | Cobranças | Assinaturas | Webhooks | Chaves Pix |
| --- | :---: | :---: | :---: | :---: | :---: |
| **Asaas** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Woovi/OpenPix** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Mercado Pago** | ✅ | ✅ | ✅ | — | — |
| **PagBank** | ✅ | ✅ | ✅ | — | — |
| **Pagar.me** | ✅ | ✅ | ✅ | — | — |
| **AbacatePay** | ✅ | ✅ | — | — | — |
| **Cielo** | — | ✅ | ✅ | — | — |
| **Rede** | — | ✅ | — | — | — |
| **Efí** | — | ✅ | — | — | — |

As interfaces correspondentes são `SupportsCustomers`, `SupportsCharges`,
`SupportsSubscriptions`, `SupportsWebhooks` e `SupportsPixKeys`.

Duas colunas merecem explicação, porque a ausência de ✅ **não** quer dizer que o
gateway não aceita Pix ou não manda webhook:

- **`SupportsPixKeys`** significa *gerenciar chaves Pix e QR Code estático*, o
  que só um PSP que emite chave própria oferece. Nos outros gateways, Pix é
  forma de pagamento de uma cobrança — e todos aceitam.
- **`SupportsWebhooks`** significa *cadastrar endpoints pela API*. Nos outros,
  o cadastro é no painel; a notificação vai por cobrança, no campo
  `notification_url`. O Pagar.me ainda deixa **consultar e reenviar entregas**,
  através de [`webhookDeliveries()`](#consultando-entregas-de-webhook).

---

## Requisitos

| | |
| --- | --- |
| **Para usar a biblioteca** | PHP `^8.1`, `ext-curl`, `ext-json` |
| **Para desenvolver o PHPay** | PHP `^8.2` (Pest 3 e Termwind 2 exigem) |

A compatibilidade com PHP 8.1 é verificada estaticamente pelo PHPStan a cada
build, com `phpVersion` mínimo configurado.

---

## Instalação

```bash
composer require phpay-io/phpay
```

---

## Início rápido

Uma cobrança Pix no Asaas, do zero:

```php
use PHPay\Asaas\AsaasGateway;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

try {
    $cobranca = $phpay->charge()
        ->setCharge([
            'billingType' => 'PIX',
            'value'       => 100.50,
            'dueDate'     => date('Y-m-d', strtotime('+3 days')),
            'description' => 'Assinatura PHPay',
        ])
        ->setCustomer([
            'name'    => 'Mário Lucas',
            'cpfCnpj' => '12345678901',
        ])
        ->create();

    print_r($phpay->charge()->getQrCodePix($cobranca['id']));
} catch (PHPayException $e) {
    echo $e->getMessage();
}
```

Um array devolvido é **sempre** uma resposta de sucesso. Qualquer falha vira
exceção — veja [Tratamento de erros](#tratamento-de-erros).

---

## Conceitos

Três coisas valem entender uma vez; depois todo gateway se comporta igual.

### Capacidades

`GatewayInterface` carrega só a identidade do gateway. Cada recurso é uma
interface que o gateway implementa **se, e só se,** oferecer:

```php
use PHPay\Contracts\Capability;

$phpay = PHPay::gateway(new EfiGateway(CLIENT_ID, CLIENT_SECRET));

$phpay->name();                                 // 'Efí'
$phpay->supports(Capability::SUBSCRIPTIONS);    // false
$phpay->capabilities();                          // [Capability::CHARGES]
```

Chamar um recurso que o gateway não oferece lança uma exceção que diz o que ele
oferece:

```php
$phpay->pix();
// NotImplementedException: Efí não suporta chaves Pix.
//                          Capacidades disponíveis: cobranças.
```

Se você segurar o **gateway concreto** em vez da facade, o erro sobe para tempo
de análise — o PHPStan acusa que o método não existe naquele tipo:

```php
$efi = new EfiGateway(CLIENT_ID, CLIENT_SECRET);

$efi->charge();   // ✅
$efi->pix();      // ❌ o método não existe nesse gateway
```

Para injeção de dependência, tipe a capacidade em vez do gateway:

```php
use PHPay\Contracts\SupportsCharges;

public function __construct(private SupportsCharges $gateway) {}
```

### Tratamento de erros

Toda exceção da biblioteca implementa `PHPay\Exceptions\PHPayException`, então
um `catch` cobre a integração inteira:

| Exceção | Estende | Quando acontece |
| --- | --- | --- |
| `ValidationException` | `InvalidArgumentException` | Payload inválido, **antes** de qualquer HTTP |
| `ApiException` | `RuntimeException` | O gateway recusou, ou está inacessível |
| `NotImplementedException` | `BadMethodCallException` | O gateway não oferece o recurso |

```php
use PHPay\Exceptions\ApiException;
use PHPay\Exceptions\PHPayException;
use PHPay\Exceptions\ValidationException;

try {
    $cobranca = $phpay->charge()->setCharge($dados)->create();
} catch (ValidationException $e) {
    // payload inválido: nenhuma requisição foi feita
} catch (ApiException $e) {
    $e->getStatusCode();       // 400, 401, 404… ou 0 se nem chegou ao gateway
    $e->getResponse();         // corpo devolvido pelo gateway
    $e->getGateway();          // 'Asaas', 'Pagar.me', …
    $e->isConnectionError();   // true em timeout, DNS, TLS — vale retry
} catch (PHPayException $e) {
    // qualquer outra falha do PHPay
}
```

`ApiException` já resume os formatos de erro de cada gateway, então
`getMessage()` traz a descrição legível, não um dump.

### Ambientes e credenciais

Os gateways discordam sobre como separar teste de produção, e o PHPay segue o
que cada um faz em vez de inventar um padrão:

| Gateway | Como o ambiente é decidido |
| --- | --- |
| **Asaas** | `$sandbox` no construtor — troca a URL |
| **PagBank** | `$sandbox` no construtor — troca a URL |
| **Cielo** | `$sandbox` no construtor — troca **as duas** URLs |
| **Efí** | `$sandbox` no construtor — troca a URL |
| **Rede** | `$sandbox` no construtor — troca **as duas** URLs e o caminho do token |
| **Mercado Pago** | Prefixo do token (`TEST-`); host único, sem `$sandbox` |
| **Pagar.me** | Prefixo da chave (`sk_test_`); host único, sem `$sandbox` |
| **Woovi** | `$sandbox` no construtor — o sandbox tem **domínio próprio** |
| **AbacatePay** | Pela chave usada; host único, **sem prefixo** — a cobrança informa em `devMode` |

Nos dois últimos, `isSandbox()` diz em qual ambiente você está:

```php
(new MercadoPagoGateway($token))->isSandbox();
(new PagarMeGateway($secretKey))->isSandbox();
```

> **Nunca** versione credenciais. Os arquivos `examples/*/credentials.php` são
> ignorados pelo git por padrão.

### Cliente

O mesmo campo tem **seis grafias** entre os gateways: `cpfCnpj` no Asaas,
`tax_id` no PagBank, `document` no Pagar.me, `taxId` no AbacatePay, `taxID` no
Woovi, `cpf_cnpj` no Efí. Código escrito para um não migra para outro, e nada
no tipo avisa.

`Customer` é a forma única. Cada gateway mapeia para o formato dele:

```php
use PHPay\Support\Customer;

$cliente = Customer::make(
    name: 'Mário Lucas',
    document: '123.456.789-01',     // pontuação é limpa
    email: 'fale@phpay.io',
    phone: '(11) 94002-8922',
);

$phpay->charge()->setCustomer($cliente);   // funciona nos nove
```

Ele também carrega o que cada gateway deriva do cliente, e que antes ficava
espalhado:

```php
$cliente->isIndividual();   // CPF: o Pagar.me precisa como type: 'individual'
$cliente->documentType();   // 'CPF' | 'CNPJ': a Cielo quer em IdentityType
$cliente->firstName();      // o Mercado Pago quer nome e sobrenome separados
$cliente->phoneParts();     // ['country' => '55', 'area' => '11', ...] para o PagBank
```

Para um cliente que já existe no gateway, ou para campos que só aquele gateway
tem:

```php
$cliente->withId('cus_000006337812');            // reaproveita em vez de criar
$cliente->withExtra(['externalReference' => 'x']); // vai junto no payload
```

> O `withExtra()` existe para o que é específico de um gateway — endereço,
> data de nascimento, referência externa. **Nenhum campo obrigatório de
> nenhum dos nove gateways precisa dele**: o value object cobre todos.

### Unidade monetária

Os gateways discordam sobre a unidade, e **errar não quebra a integração — ela
cobra o valor errado**. Mandar `100.50` num gateway de centavos cobra R$ 1,00,
e você só descobre na conciliação.

Use `Money` e o problema deixa de existir: você diz a unidade que tem, o
gateway pede a unidade que precisa, e nenhum dos dois pode errar.

```php
use PHPay\Support\Money;

$valor = Money::reais(100.50);     // ou Money::centavos(10050)

$asaas->charge()->setAmount($valor);          // vira 100.50
$pagbank->charge()->addItem('Item', $valor);  // vira 10050
```

Também aceita string, que é o que um campo de formulário costuma entregar:

```php
Money::reais('100,50');      // notação brasileira
Money::reais('1.234,56');    // com separador de milhar
Money::reais('100.50');      // notação com ponto
```

E tem o que um total precisa:

```php
$unitario = Money::reais(59.90);

$unitario->multiply(2);              // R$ 119,80
$unitario->plus(Money::reais(10));   // R$ 69,90
$unitario->format();                 // 'R$ 59,90'
```

> `Money::reais(10 / 3)` **lança exceção** em vez de arredondar. Arredondamento
> silencioso é como nascem erros de um centavo na conciliação — arredonde você
> mesmo, ou use `Money::centavos()` para ser exato.

#### Passando número cru

Continua funcionando, e cada gateway lê na unidade que sempre esperou:

| Gateway | Unidade do número cru | R$ 100,50 |
| --- | --- | --- |
| **Asaas**, **Mercado Pago** | Reais (decimal) | `100.50` |
| **PagBank**, **Pagar.me**, **Cielo**, **Rede**, **AbacatePay**, **Woovi**, **Efí** | Centavos (inteiro) | `10050` |

Nos que usam centavos, o PHPay recusa decimal na validação. Mas é justamente
essa tabela que o `Money` torna desnecessária — **prefira o value object**.

---

## Gateways

Cada seção cobre só o que é específico daquele gateway. Tudo que vale para
todos está em [Conceitos](#conceitos).

### Asaas

O único com as cinco capacidades — é PSP, então emite chave Pix própria e
gerencia webhooks por API.

```php
use PHPay\Asaas\AsaasGateway;

$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));
```

#### Cobranças

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

#### Clientes

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

#### Assinaturas

```php
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX))->subscription();

$phpay->setCustomer($cliente)->create([
    'billingType' => 'BOLETO',
    'value'       => 100,
    'nextDueDate' => '2026-04-09',
    'cycle'       => 'MONTHLY',
]);

/* ou com um cliente existente */
$phpay->setCustomerId('cus_000006337812')->create([...]);
```

#### Webhooks e chaves Pix

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

### Mercado Pago

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

### PagBank

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

### Pagar.me

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

#### Consultando entregas de webhook

O Pagar.me deixa ler e reenviar os eventos que já despachou. Isso **não** é a
capacidade `SupportsWebhooks` — o cadastro dos endpoints é no dashboard — então
vive no gateway concreto, não na facade:

```php
$gateway->webhookDeliveries()->setFilter(['size' => 10])->getAll();
$gateway->webhookDeliveries()->resend($hookId);
```

É assim que o modelo de capacidades abre espaço para o que só um gateway
oferece: quem segura `PagarMeGateway` alcança, quem tipa uma capacidade não.

### Cielo

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
```

#### Recorrência

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
### Rede

Adquirente, e a forma mais estreita da biblioteca junto com o Efí: **só
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

### AbacatePay

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

#### Cupons de desconto

Nenhum outro gateway da biblioteca tem isso, então não é capacidade: vive no
gateway concreto, como o `webhookDeliveries()` do Pagar.me.

```php
$gateway->coupons()->create([
    'code'         => 'PHPAY10',
    'discountKind' => 'PERCENTAGE',
    'discount'     => 10,
]);
```

### Woovi/OpenPix

**O segundo gateway com as cinco capacidades**, ao lado do Asaas — e o que
confirma que o modelo descreve o domínio, não um fornecedor: são duas empresas
independentes, com APIs independentes, preenchendo o mesmo contrato.

Sendo PSP Pix-nativo, ele gerencia chaves e QR Code estático de verdade.

```php
use PHPay\Woovi\Enums\PixKeyTypeEnum;
use PHPay\Woovi\WooviGateway;

$phpay = PHPay::gateway(new WooviGateway(WOOVI_APP_ID));

/* chaves Pix da conta */
$phpay->pix()->createKey(PixKeyTypeEnum::RANDOM);
$phpay->pix()->getAll();

/* consulta uma chave antes de pagar */
$phpay->pix()->verifyKey('fale@phpay.io');

/* QR Code estático, com ou sem valor */
$phpay->pix()->staticQrCode('Caixa 1');
$phpay->pix()->staticQrCode('Caixa 2', 2500);
```

Três particularidades:

**O AppID vai cru no `Authorization`** — sem `Bearer`, sem `Basic`.

**O sandbox tem domínio próprio**: `api.woovi-sandbox.com` contra
`api.openpix.com.br`.

**Todo objeto é endereçável pelo `correlationID`**, o id no *seu* sistema —
nenhum outro gateway da biblioteca oferece isso:

```php
$cobranca = $phpay->charge()
    ->setCorrelationId('pedido-1')
    ->setCustomer(['name' => 'Mário Lucas', 'email' => 'fale@phpay.io'])
    ->create(10050);   // R$ 100,50

$phpay->charge()->getPixCode($cobranca);
$phpay->charge()->find('pedido-1');      // pelo SEU id, não pelo do gateway
```

Webhooks têm CRUD por API — junto com o Asaas, os únicos:

```php
$phpay->webhook(['name' => 'PHPay', 'url' => 'https://exemplo.test/webhook'])->create();
$phpay->webhook()->getAll();
```

> Repare que o webhook fica em `api/openpix/v1/`, enquanto os demais recursos
> ficam em `api/v1/` — herança da fusão das duas marcas. O PHPay trata isso
> internamente.

### Efí

Só cobranças, por enquanto. O gateway **não faz chamada de rede no construtor**
— a autorização acontece na primeira vez que o token é necessário, e uma vez só.

```php
use PHPay\Efi\EfiGateway;

$gateway = new EfiGateway(CLIENT_ID, CLIENT_SECRET);

$cobranca = PHPay::gateway($gateway)->charge([
    'value'       => 10050,   // R$ 100,50 — o Efí usa centavos
    'description' => 'Assinatura PHPay',
    'expire_at'   => date('Y-m-d', strtotime('+3 days')),
])
    ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
    ->create();
```

---

## Exemplos executáveis

O diretório [`examples/`](./examples) traz scripts prontos por gateway. Copie o
`credentials.example.php` para `credentials.php`, preencha, e rode:

```bash
php examples/asaas/charges.php
```

Dois gateways têm também uma **checagem de conformidade**, que roda contra o
sandbox de verdade e relata cada operação. Teste com HTTP mockado prova que a
biblioteca monta o payload que decidimos; isto prova que o gateway o aceita:

```bash
MP_ACCESS_TOKEN='TEST-...' php examples/mercadopago/sandbox-check.php
PAGBANK_TOKEN='...'        php examples/pagbank/sandbox-check.php
```

Os dois recusam credenciais de produção e nunca imprimem o token.

---

## Migrando da v1

A v2.0.0 tem breaking changes — a principal é que falhas passaram a ser exceção
em vez de array de erro. O de-para completo, quebra por quebra, está em
**[UPGRADE.md](./UPGRADE.md)**.

Dois pontos merecem auditoria de quem vem da v1:

1. Falhas que antes voltavam como array e passavam despercebidas agora
   **interrompem o fluxo**. É o comportamento correto, mas expõe caminhos que
   nunca foram exercitados.
2. Integrações que chamavam `setCustomer()` em laço **provavelmente acumularam
   clientes duplicados** no gateway.

---

## Roadmap

### Plataforma

| Item | Status |
| --- | :---: |
| Definições de arquitetura | ✅ |
| Capacidades por gateway | ✅ |
| Tratamento de erros por exceção | ✅ |
| Testes com HTTP mockado | ✅ |
| CI no GitHub Actions | ✅ |
| Guia de migração | ✅ |
| Documentação | ✍️ |
| Site | 🕛 |

### Cobertura por gateway

| Gateway | Cobranças | Clientes | Assinaturas | Webhooks | Pix |
| --- | :---: | :---: | :---: | :---: | :---: |
| **Asaas** | ✅ | ✅ | ✍️ | ✅ | ✅ |
| **Woovi/OpenPix** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Mercado Pago** | ✅ | ✅ | ✅ | — | ✅ |
| **PagBank** | ✅ | ✅ | ✅ | — | ✅ |
| **Pagar.me** | ✅ | ✅ | ✅ | leitura ✅ | ✅ |
| **AbacatePay** | ✅ | ✅ | — | — | ✅ |
| **Cielo** | ✅ | — | ✅ | — | ✅ |
| **Rede** | ✅ | — | — | — | 🕥 |
| **Efí** | ✅ | 🕥 | 🕥 | 🕥 | 🕥 |

**✅** pronto · **✍️** parcial · **🕥** planejado · **—** não existe na API do gateway

> Assinaturas do Asaas: criação pronta; listar, atualizar e cancelar pendentes.

---

## Contribuindo

Leia o [manual de contribuição](./CONTRIBUTING.md). Ele cobre o ambiente de
desenvolvimento, o gate de qualidade e as convenções do projeto.

Contribuições enviadas a partir de 2026-09-20 estão sujeitas ao
[Contributor License Agreement](./CLA.md), aceito por checkbox no pull request.

```bash
composer install
composer test     # Pint + Pest + PHPStan nível 9
```

Nenhum teste pode acessar a rede: os recursos aceitam um `GuzzleHttp\Client`
injetado, e a suíte usa mocks.

---

## Segurança

Encontrou uma vulnerabilidade? Não abra issue pública — siga a
[política de segurança](./.github/SECURITY.md).

Esta é uma biblioteca de pagamentos: nunca logue, imprima ou versione tokens,
`access_token`, `clientSecret` ou CPF/CNPJ reais.

---

## Licença

O PHPay é distribuído sob a **[Business Source License 1.1](./LICENSE.md)** a
partir da versão 2.0.0. É uma licença *source-available*: o código é aberto e
auditável, com uma única restrição comercial.

**O resumo abaixo não substitui a licença** — ele existe só para você saber
rápido se precisa ler o texto completo.

| | |
| --- | --- |
| ✅ **Pode** | Usar em produção, inclusive em software fechado e comercial |
| ✅ **Pode** | Processar pagamentos seus ou dos seus clientes |
| ✅ **Pode** | Modificar, forkar, estudar e redistribuir |
| ❌ **Não pode** | Oferecer o PHPay, ou um derivado, como biblioteca, SDK ou serviço de integração de pagamentos que concorra com ele |

Ou seja: se você integra pagamentos **no seu produto**, nada muda para você. A
restrição atinge apenas quem quiser revender o próprio PHPay.

Em **2030-09-20** a licença converte automaticamente para **MIT**, e cada
versão converte no máximo quatro anos após ser publicada.

> As versões **1.0.0 e 1.0.1** foram publicadas sob MIT e **permanecem sob
> MIT** — uma licença nova não é retroativa. O texto está preservado em
> [LICENSE-MIT.md](./LICENSE-MIT.md).

Precisa de termos diferentes? Escreva para [fale@phpay.io](mailto:fale@phpay.io).

---

<p align="center">
    Feito por <a href="https://github.com/mariolucasdev">Mário Lucas</a> ·
    <a href="mailto:fale@phpay.io">fale@phpay.io</a>
</p>
