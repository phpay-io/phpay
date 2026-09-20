![capa-redes](https://github.com/user-attachments/assets/20051d3a-ecbf-4d01-8c29-50c43b8d3af4)

<p align="center">
    <a href="https://github.com/phpay-io/phpay/releases"><img src="https://poser.pugx.org/phpay-io/phpay/v/stable" alt="Stable Version"></a>
    <a href="https://www.php.net"><img src="https://img.shields.io/badge/php-%3E=8.1-brightgreen.svg?maxAge=2592000" alt="Php Version"></a>
    <a href="https://packagist.org/packages/phpay-io/phpay"><img src="https://poser.pugx.org/phpay-io/phpay/downloads" alt="Total Downloads"></a>
</p>

O PHPay é uma biblioteca PHP que tem o objetivo tornar o trabalho de integrações com gateways de pagamento mais simples e descomplicadas, facilitando a conexão entre tecnologia e negócios em produtos de software.

## 💸 Gateways

- Asaas (cobranças, clientes, webhooks, chaves Pix e assinaturas)
- Mercado Pago (cobranças, clientes e assinaturas)
- Efí (cobranças)

## ⬆️ Vindo da v1?

A v2.0.0 tem breaking changes — a principal é que falhas passaram a ser exceção
em vez de array de erro. O de-para completo está em
[UPGRADE.md](./UPGRADE.md).

## 📦 Instalação

Instale via Composer:

```php
composer require phpay-io/phpay
```

## ⚙️ Como usar o PHPay?

```php
/**
 * instance with gateway inject
 * @var AsaasGateway $phpay
 */
$phpay = (new PHPay(new AsaasGateway(TOKEN_ASAAS_SANDBOX)));
```

### Cobranças

```php
/**
 * instance with gateway inject and resource call
 *
 * @var Charge $phpay
 */
$phpay = (new PHPay(new AsaasGateway(TOKEN_ASAAS_SANDBOX)))->charge();

/**
 * create charge
 */
$phpay
    ->setCharge($charge)
    ->setCustomer($customer)
    ->create();

/**
 * reaproveitando um cliente que já existe no gateway
 * (setCustomer cria um cliente novo quando o array não traz `id`)
 */
$phpay
    ->setCharge($charge)
    ->setCustomerId('cus_000006337812')
    ->create();

/**
 * find charge
 */
$phpay->find($chargeId);

/**
 * get all charges
 */
$phpay->getAll();

/**
 * get all charges with filters
 */
$phpay
    ->setQueryParams(['limit' => 2])
    ->getAll();

/**
 * update charge
 */
$phpay->update($chargeId, $data);

/**
 * destroy charge
 */
$phpay->destroy($chargeId);

/**
 * restore charge
 */
$phpay->restore($chargeId);

/**
 * get status charge
 */
$phpay->getStatus($chargeId);

/**
 * get digitable line charge
 */
$phpay->getDigitableLine($chargeId);

/**
 * get qrcode charge
 */
$phpay->getQrCodePix($chargeId);

/**
 * confirm receipt charge
 */
$phpay->confirmReceipt($chargeId, [
    'paymentDate'    => date('Y-m-d'),
    'value'          => 100.00,
    'notifyCustomer' => true,
]);

/**
 * undo confirm receipt
 */
$phpay->undoConfirmReceipt($chargeId);

```

### Assinaturas

```php
/**
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX))->subscription();

/**
 * create a new subscription
 */
$phpay->setCustomer($customer)->create([
    'billingType' => 'BOLETO',
    'value'       => 100,
    'nextDueDate' => '2025-04-09',
]);
```

### Assinaturas com cliente existente

```php
$phpay
    ->setCustomerId('cus_000006337812')
    ->create([
        'billingType' => 'BOLETO',
        'value'       => 100,
        'nextDueDate' => '2026-04-09',
        'cycle'       => 'MONTHLY',
    ]);
```

## 🧩 Capacidades por gateway

Nem todo gateway oferece todo recurso. Cada gateway **declara** o que suporta
através de interfaces de capacidade, em vez de o contrato ser a união de tudo:

| Capacidade | Interface | Asaas | Mercado Pago | Efí |
| --- | --- | :---: | :---: | :---: |
| Clientes | `SupportsCustomers` | ✅ | ✅ | — |
| Cobranças | `SupportsCharges` | ✅ | ✅ | ✅ |
| Webhooks | `SupportsWebhooks` | ✅ | — | — |
| Chaves Pix | `SupportsPixKeys` | ✅ | — | — |
| Assinaturas | `SupportsSubscriptions` | ✅ | ✅ | — |

> O Mercado Pago não expõe CRUD de webhooks por API: eles são configurados no
> painel "Suas integrações", ou por pagamento através do campo `notification_url`.

> `SupportsPixKeys` é mais estreito que "aceita Pix": ele significa gerenciar
> chaves e QR Code estático, algo que só um PSP que emite chave própria oferece.
> Na maioria dos gateways, Pix é uma forma de pagamento da cobrança.

Para decidir em tempo de execução:

```php
use PHPay\Contracts\Capability;

$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

$phpay->supports(Capability::SUBSCRIPTIONS);  // true
$phpay->capabilities();                        // todas as capacidades do gateway
$phpay->name();                                // 'Asaas'
```

Chamar um recurso que o gateway não oferece lança `NotImplementedException`
dizendo o que ele oferece:

```php
PHPay::gateway(new EfiGateway(CLIENT_ID, CLIENT_SECRET))->pix();
// NotImplementedException: Efí não suporta chaves Pix.
//                          Capacidades disponíveis: cobranças.
```

Se você segurar o gateway concreto em vez da facade, o erro sobe para tempo de
análise — o PHPStan acusa que o método não existe:

```php
$efi = new EfiGateway(CLIENT_ID, CLIENT_SECRET);
$efi->charge();   // ✅
$efi->pix();      // ❌ o método não existe nesse gateway
```

## 🚨 Tratamento de erros

Toda falha vira exceção — um array de retorno é **sempre** uma resposta de sucesso.
Todas as exceções da biblioteca implementam `PHPay\Exceptions\PHPayException`, então
um único `catch` cobre a integração inteira:

```php
use PHPay\Exceptions\ApiException;
use PHPay\Exceptions\NotImplementedException;
use PHPay\Exceptions\PHPayException;
use PHPay\Exceptions\ValidationException;

try {
    $charge = $phpay->setCharge($charge)->setCustomer($customer)->create();
} catch (ValidationException $e) {
    /* payload inválido: nenhuma requisição foi feita */
    echo $e->getMessage();
} catch (ApiException $e) {
    /* o gateway recusou a requisição ou está inacessível */
    echo $e->getMessage();
    echo $e->getStatusCode();       // 400, 401, 404... ou 0 se nem chegou ao gateway
    print_r($e->getResponse());     // corpo devolvido pelo gateway
    echo $e->getGateway();          // 'Asaas' ou 'Efí'

    if ($e->isConnectionError()) {
        /* timeout, DNS, TLS — vale um retry */
    }
} catch (NotImplementedException $e) {
    /* o gateway ainda não implementa esse recurso */
} catch (PHPayException $e) {
    /* qualquer outra falha do PHPay */
}
```

## 💳 Mercado Pago

O Mercado Pago não tem URL de sandbox — o ambiente vem do próprio token, que é
prefixado com `TEST-` nas credenciais de teste:

```php
use PHPay\MercadoPago\Enums\PaymentMethodEnum;
use PHPay\MercadoPago\MercadoPagoGateway;

$gateway = new MercadoPagoGateway(ACCESS_TOKEN_MERCADO_PAGO);

$gateway->isSandbox();   // true para tokens TEST-
```

Cobrança via Pix — aqui o Pix é forma de pagamento, não um recurso à parte:

```php
$charge = PHPay::gateway($gateway)
    ->charge()
    ->setCharge([
        'transaction_amount' => 100.00,
        'payment_method_id'  => PaymentMethodEnum::PIX->value,
        'description'        => 'Cobrança de teste',
        'notification_url'   => 'https://exemplo.test/webhook/mercadopago',
    ])
    ->setPayer(['email' => 'comprador@exemplo.test'])
    ->setIdempotencyKey('pedido-123456')
    ->create();

$phpay->getPixCode($charge['id']);   // código copia-e-cola
```

`POST /v1/payments` exige o header `X-Idempotency-Key`. O PHPay gera uma chave
por chamada; passe a sua com `setIdempotencyKey()` para que um retry da mesma
operação de negócio não gere duas cobranças.

Para conferir contra o sandbox de verdade — algo que teste com HTTP mockado não
prova — rode a checagem de conformidade com um token de teste:

```bash
MP_ACCESS_TOKEN='TEST-...' php examples/mercadopago/sandbox-check.php
```

O script recusa credenciais de produção e nunca imprime o token.

Assinaturas usam `/preapproval`, com ou sem plano associado:

```php
$phpay = PHPay::gateway($gateway)->subscription();

$phpay->setPayerEmail('comprador@exemplo.test')->create([
    'reason'         => 'Assinatura PHPay',
    'back_url'       => 'https://exemplo.test/retorno',
    'auto_recurring' => [
        'frequency'          => 1,
        'frequency_type'     => 'months',
        'transaction_amount' => 100.00,
        'currency_id'        => 'BRL',
    ],
]);

/* com plano, a recorrência vem do plano */
$phpay->setPayerEmail('comprador@exemplo.test')
    ->setPlan('2c938084726fca480172750000000000')
    ->create(['back_url' => 'https://exemplo.test/retorno']);
```

## 📝 Roadmap

- Definições de Arquitetura ✅
- Tratamento de erros por exceção ✅
- Testes com HTTP mockado ✅
- CI no GitHub Actions ✅
- Domínios ✅
- Documentação ✍️
- Site 🕛
- Gateways ✍️

  - Asaas.

  - Cobranças ✅
  - Clientes ✅
  - Webhook ✅
  - Pix (chaves e QR Code estático) ✅
  - Assinaturas ✍️ (criação pronta; listar/atualizar/cancelar pendentes)

  - Mercado Pago.

  - Cobranças ✅
  - Clientes ✅
  - Assinaturas ✅
  - Webhook — sem CRUD por API
  - Pix ✅ (como forma de pagamento)

  - Efí.

  - Autorização ✅
  - Cobranças ✅
  - Clientes 🕥
  - Webhook 🕥
  - Assinaturas 🕥
  - Pix 🕥

- Lançamento v2.0.0 🚀 (contém breaking changes — veja a seção de tratamento de erros)

## 🌟 Contribuindo

Para contribuir com o PHPay, implementando melhorias e novos gateways de pagamento,
leia nosso manual de contribuição. [MANUAL DE CONTRIBUIÇÃO PHPAY](./CONTRIBUTING.md)

## 📄 Licença

Este projeto está licenciado sob a MIT License. Consulte o arquivo [LICENSE](./LICENSE.md) para mais detalhes.

## 🤝 Contato

💻 GitHub: [Mário Lucas](https://github.com/mariolucasdev)

📧 Email: fale@phpay.io

🎉 Comece a usar o PHPay e simplifique suas integrações com gateways de pagamento!
