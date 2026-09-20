# Guia de migração

## v1.x → v2.0.0

A v2 é a primeira versão com quebras de compatibilidade desde o lançamento.
Todas as mudanças estão listadas abaixo, da mais impactante para a menos.

### Requisitos

Nada muda para consumir a biblioteca: continua `PHP ^8.1`.

Para **desenvolver** o PHPay agora é preciso `PHP ^8.2`, porque Pest 3 e
Termwind 2 exigem isso. A compatibilidade com 8.1 é garantida estaticamente
pelo `phpVersion` do `phpstan.neon`.

---

### 1. Falhas viram exceção, não array de erro

**Esta é a mudança que mais afeta código existente.**

Na v1, uma falha voltava como array e tinha o mesmo tipo de uma resposta de
sucesso — uma cobrança que falhou era indistinguível de uma criada:

```php
// v1
$charge = $phpay->charge()->setCharge($dados)->create();

if (isset($charge['error'])) {
    // tratamento
}
```

Na v2, **um array retornado é sempre sucesso**. Qualquer falha lança:

```php
// v2
use PHPay\Exceptions\ApiException;
use PHPay\Exceptions\PHPayException;
use PHPay\Exceptions\ValidationException;

try {
    $charge = $phpay->charge()->setCharge($dados)->create();
} catch (ValidationException $e) {
    // payload inválido — nenhuma requisição foi feita
} catch (ApiException $e) {
    $e->getStatusCode();        // 400, 401, 404… ou 0 se nem chegou ao gateway
    $e->getResponse();          // corpo devolvido pelo gateway
    $e->getGateway();           // 'Asaas', 'Efí', 'Mercado Pago'
    $e->isConnectionError();    // true em timeout, DNS, TLS — vale retry
}
```

Todas as exceções implementam `PHPayException`, então um único `catch` cobre a
integração inteira.

`destroy()` continua devolvendo `bool`, mas agora só devolve `false` quando o
gateway de fato recusou a exclusão — erro de rede lança.

> **Atenção:** quem não tratava o array de erro tinha uma falha silenciosa. Na
> v2 essa mesma falha passa a interromper o fluxo. É o comportamento correto,
> mas pode expor caminhos que nunca foram exercitados.

---

### 2. Namespaces unificados

A v1 tinha três convenções para o mesmo código. Todas viraram uma:

| v1 | v2 |
| --- | --- |
| `PHPay\Gateways\Asaas\Enums\*` | `PHPay\Asaas\Enums\*` |
| `PHPay\Gateways\Asaas\Requests\*` | `PHPay\Asaas\Requests\*` |
| `Efi\Resources\*`, `Efi\Traits\*`, `Efi\Interface\*` | `PHPay\Efi\*` |
| `Asaas\Resources\Webhook\Enum\WebhookEventsEnum` | `PHPay\Asaas\Resources\Webhook\Enum\WebhookEventsEnum` |

`PHPay\Asaas\*`, `PHPay\Efi\*` e `PHPay\PHPay` **não mudaram** — se você só usava
esses, não há o que ajustar.

Os roots PSR-4 `Efi\` e `AsaasCustomer\` foram removidos.

---

### 3. Gateways declaram capacidades

Na v1, `GatewayInterface` exigia os cinco recursos de todo gateway, e quem não
suportava lançava de dentro de um stub. Na v2 o contrato base tem só `name()`, e
cada recurso é uma interface que o gateway implementa se — e só se — oferecer:

```php
use PHPay\Contracts\Capability;

$phpay = PHPay::gateway(new EfiGateway(ID, SECRET));

$phpay->supports(Capability::SUBSCRIPTIONS);   // false
$phpay->capabilities();                         // [Capability::CHARGES]
$phpay->name();                                 // 'Efí'
```

**Se você tipava `GatewayInterface` e chamava recursos**, passe a tipar a
capacidade:

```php
// v1
function cobrar(GatewayInterface $gateway) { $gateway->charge(); }

// v2
use PHPay\Contracts\SupportsCharges;

function cobrar(SupportsCharges $gateway) { $gateway->charge(); }
```

Ou use a facade `PHPay`, que continua aceitando qualquer gateway e lança
`NotImplementedException` nomeando o que aquele gateway oferece.

---

### 4. `setCustomer()` não cria mais cliente duplicado

Na v1, `setCustomer()` chamava a criação de cliente a cada invocação: duas
cobranças para a mesma pessoa geravam dois cadastros no gateway.

Na v2, quando o array traz um `id`, o cliente é reaproveitado. Para o caminho
explícito existe `setCustomerId()`:

```php
// cria o cliente (como na v1)
$phpay->charge()->setCharge($dados)->setCustomer(['name' => ..., 'cpfCnpj' => ...]);

// reaproveita um cliente existente
$phpay->charge()->setCharge($dados)->setCustomerId('cus_000006337812');
```

Vale auditar integrações da v1 que chamavam `setCustomer()` em laço — elas
provavelmente acumularam clientes duplicados no gateway.

---

### 5. `pix()` não recebe mais argumento

O array era aceito e descartado em toda a cadeia.

```php
$phpay->pix([...]);   // v1
$phpay->pix();        // v2
```

---

### 6. `messages()` de cobrança e assinatura ficou plano

```php
AsaasChargeRequest::messages()->charge->customer;   // v1
AsaasChargeRequest::messages()->customer;           // v2
```

O ramo `->customer->id`, que nenhum código lia, foi removido. As mensagens
também deixaram de carregar o prefixo do gateway no texto — quem prefixa agora é
a exceção.

---

### 7. Classes removidas

| Removida | Por quê |
| --- | --- |
| `PHPay\Exceptions\AsaasExceptions` | Não era usada, e declarava retornar `Exception` num método que lançava |
| `PHPay\Gateways\Asaas\Requests\AsaasPixRequest` | Não era chamada, e seus campos não correspondem a nenhum endpoint de Pix do Asaas |

---

### 8. `AsaasGateway` perdeu propriedades públicas

`$client` e `$customer` eram públicas e sem uso — `$customer` inclusive colidia
conceitualmente com o método de mesmo nome. Use os métodos.

---

### 9. Efí: autorização sob demanda

O construtor de `EfiGateway` fazia uma requisição HTTP. Agora a autorização
acontece na primeira vez que o token é necessário, e só uma vez. Construir o
gateway deixou de ser uma operação que pode falhar.

`Efi\Resources\Charge\Charge::getStatus()` apontava para `payments/{id}/status`,
endpoint do Asaas — sempre 404 na base da Efí. Agora usa `v1/charge/{id}`.

---

## Novidade: gateway Mercado Pago

```php
use PHPay\MercadoPago\Enums\PaymentMethodEnum;
use PHPay\MercadoPago\MercadoPagoGateway;

$gateway = new MercadoPagoGateway(ACCESS_TOKEN);

$gateway->isSandbox();   // vem do prefixo TEST- do token; não há url de sandbox

PHPay::gateway($gateway)->charge()
    ->setCharge([
        'transaction_amount' => 100.00,
        'payment_method_id'  => PaymentMethodEnum::PIX->value,
        'notification_url'   => 'https://exemplo.test/webhook/mercadopago',
    ])
    ->setPayer(['email' => 'comprador@exemplo.test'])
    ->setIdempotencyKey('pedido-123456')
    ->create();
```

Três pontos que diferem dos outros gateways:

- **Não há URL de sandbox.** O ambiente vem do prefixo `TEST-` do access token,
  então o construtor não recebe `$sandbox`.
- **`POST /v1/payments` exige `X-Idempotency-Key`.** O PHPay gera uma chave por
  chamada; passe a sua com `setIdempotencyKey()` para que o retry da mesma
  operação de negócio não crie duas cobranças.
- **Não declara webhooks nem chaves Pix.** Webhooks não têm CRUD por API — são
  configurados no painel "Suas integrações" ou por pagamento, via
  `notification_url`. E Pix lá é forma de pagamento, não recurso com chaves.
