# Status de pagamento

Use **`PHPay\Support\PaymentStatus`**. É o mesmo problema que o
[`Money`](valores-monetarios.md) e o [`Customer`](cliente.md) resolvem na
entrada, resolvido na saída: dez vocabulários para os mesmos estados.

[← voltar ao índice](README.md)

## O problema

A mesma cobrança paga volta assim, dependendo de quem respondeu:

| Gateway | "paga" é |
| --- | --- |
| Asaas | `RECEIVED` (ou `CONFIRMED`, antes do saldo cair) |
| Woovi/OpenPix | `COMPLETED` |
| Cielo | `2` |
| Rede | `"00"` |
| AbacatePay | `PAID` |
| Mercado Pago | `approved` |
| PagBank | `PAID` |
| Pagar.me | `paid` |
| Efí (Cobranças) | `paid` |
| Efí (Pix) | `CONCLUIDA` |

Quem troca de gateway reescreve todo `if`. Quem integra dois escreve o `match`
duas vezes.

## Como usar

Cada gateway tem o seu enum de status, e todos ganharam `of()`, que lê o valor
cru da resposta, e `status()`, que traduz um case:

```php
use PHPay\Asaas\Enums\InvoiceStatusEnum;
use PHPay\Support\PaymentStatus;

$cobranca = $phpay->charge()->find($id);

$status = InvoiceStatusEnum::of($cobranca['status']);

if ($status === PaymentStatus::PAID) {
    // libera o pedido
}
```

O enum concreto **continua existindo** e continua acessível — quem precisa do
estado específico daquele gateway não perde nada:

```php
InvoiceStatusEnum::CONFIRMED->status();   // PaymentStatus::PAID
InvoiceStatusEnum::from('CONFIRMED');     // o case do Asaas, como antes
```

| Gateway | Enum |
| --- | --- |
| Asaas | `PHPay\Asaas\Enums\InvoiceStatusEnum` |
| Woovi/OpenPix | `PHPay\Woovi\Enums\ChargeStatusEnum` |
| Cielo | `PHPay\Cielo\Enums\SaleStatusEnum` (inteiro) |
| Rede | `PHPay\Rede\Enums\TransactionStatusEnum` |
| AbacatePay | `PHPay\AbacatePay\Enums\BillingStatusEnum` |
| Mercado Pago | `PHPay\MercadoPago\Enums\PaymentStatusEnum` |
| PagBank | `PHPay\PagBank\Enums\ChargeStatusEnum` |
| Pagar.me | `PHPay\PagarMe\Enums\ChargeStatusEnum` |
| Efí (Cobranças) | `PHPay\Efi\Enums\ChargeStatusEnum` |
| Efí (Pix) | `PHPay\Efi\Enums\PixChargeStatusEnum` |

> A Efí tem **dois**, porque tem duas APIs com vocabulários diferentes e as
> mesmas credenciais. Ler uma com o enum da outra devolve `UNKNOWN`, em vez de
> um palpite.

## Os treze estados

| Caso | Significa |
| --- | --- |
| `PENDING` | criada, aguardando o pagador |
| `AUTHORIZED` | pré-autorizada no cartão, aguardando captura — **o dinheiro não se moveu** |
| `IN_ANALYSIS` | retida para análise de risco |
| `PARTIALLY_PAID` | chegou dinheiro, mas menos que o devido |
| `PAID` | recebida |
| `PARTIALLY_REFUNDED` | parte foi devolvida |
| `REFUNDED` | tudo foi devolvido |
| `IN_DISPUTE` | o pagador contestou e o resultado está aberto |
| `CHARGEBACK` | a disputa foi perdida: o emissor levou o dinheiro de volta |
| `CANCELED` | cancelada antes de alguém pagar |
| `EXPIRED` | a janela fechou sem pagamento |
| `FAILED` | recusada |
| `UNKNOWN` | status que esta versão da biblioteca não conhece |

## Três coisas que não são óbvias

**`PAID` não é estado final.** Uma cobrança recebida ainda pode ser estornada
ou sofrer chargeback, e tratá-la como encerrada é como um estorno passa
despercebido:

```php
PaymentStatus::PAID->isFinal();        // false
PaymentStatus::REFUNDED->isFinal();    // true
```

Finais são `REFUNDED`, `CHARGEBACK`, `CANCELED`, `EXPIRED` e `FAILED`.

**`AUTHORIZED` não é `PAID`.** Cielo, PagBank e Mercado Pago separam os dois
porque o dinheiro ainda não se moveu — falta a captura. `isPaid()` é `false`
ali, e entregar produto nesse estado é risco:

```php
PaymentStatus::AUTHORIZED->isPaid();   // false
```

**`UNKNOWN` existe de propósito.** Gateway acrescenta status sem avisar, e a
alternativa seria a biblioteca levantar exceção ao ler uma resposta de
**sucesso** — o que contraria a [regra do modelo de erros](erros.md). Ler
estado novo como desconhecido degrada; quebrar perde a cobrança. O valor cru
continua na resposta, para quem precisar tratar o caso.

```php
InvoiceStatusEnum::of('STATUS_QUE_O_ASAAS_INVENTOU');   // PaymentStatus::UNKNOWN
```

## Duas exceções por gateway

**Na Rede, código não listado é recusa, não desconhecido.** O enum lista só
`"00"` e `"220"`, porque a Rede publica dezenas de motivos de recusa e tratá-los
como enum exaustivo quebraria a cada novo. Então qualquer outro código é
`FAILED` — responder `UNKNOWN` esconderia uma recusa atrás de "não sei". Código
**ausente** continua `UNKNOWN`, que é campo faltando, não recusa.

**No Pagar.me, estorno não se distingue de cancelamento.** Cancelar e estornar
passam pelo mesmo `DELETE` e voltam como `canceled`, então uma cobrança paga e
devolvida lê como `CANCELED`, não `REFUNDED`. É limite da API, não do
mapeamento: para separar os dois, veja se a cobrança havia sido paga.

## O que ficou de fora

**Status de assinatura.** `SubscriptionStatusEnum` do Mercado Pago e do PagBank
descrevem outro ciclo de vida — ativa, pausada, cancelada, inadimplente — que
não se mapeia nestes treze casos. Forçar os dois no mesmo enum produziria um
que não descreve bem nenhum dos dois.

**Normalizar o resto da resposta.** Um objeto único de cobrança (id, valor,
status, vencimento) muda o contrato de retorno, que hoje é array e é assim de
propósito. Esta tradução cobre o status e nada mais.
