# Fallback entre gateways

`FallbackGateway` tenta uma lista de gateways em ordem — **mas só cai para o
próximo quando cair é seguro.**

[← voltar à documentação](README.md)

```php
use PHPay\Support\{FallbackGateway, Gateway};

$gateway = new FallbackGateway([
    Gateway::WOOVI->make(['appId' => $appId]),
    Gateway::MERCADO_PAGO->make(['accessToken' => $token]),
]);

PHPay::gateway($gateway)->createCharge($intent);
```

## A regra, e por que ela é assim

Cair para o próximo gateway é o irmão do [retry](retry-e-idempotencia.md), e é
**pior**:

> Se a requisição falhou de um jeito que **não prova** que não chegou, cair
> para o próximo cria uma **segunda cobrança**. O cliente pode receber dois QR
> Codes e pagar os dois.

Um retry repetido o próprio gateway pode deduplicar por chave de idempotência.
**Entre dois PSPs não existe chave de idempotência** — são dois sistemas sem
nada em comum. Ninguém dedupe por você.

| situação | cai? | por quê |
| --- | :-: | --- |
| DNS, conexão recusada, TLS | ✅ | provadamente não saiu |
| timeout **antes** de conectar | ✅ | idem |
| 401 / 403 credencial inválida | ✅ | recusou antes de processar |
| 429 | ✅ | recusou antes de processar |
| regra do próprio gateway (sem HTTP) | ✅ | nada saiu, e a regra do próximo é outra |
| 4xx de payload | ❌ | erraria igual no próximo |
| 5xx já com a requisição na linha | ❌ | **pode ter criado a cobrança** |
| timeout com a conexão aberta | ❌ | idem |

Quem responde "saiu ou não?" é `PHPay\Http\Delivery` — **a mesma resposta que
o `RetryPolicy` usa**, nunca uma segunda cópia. Duas cópias divergiriam, e a
divergência apareceria como alguém pagando duas vezes.

## Retry vem antes, e as camadas são diferentes

```
FallbackGateway        ← tenta OUTRO gateway
      │
      ▼
RetryPolicy            ← repete a MESMA requisição, dentro de um gateway
```

Um erro que provadamente não saiu é repetido **três vezes no mesmo gateway**,
e só depois de esgotado é que o próximo da lista entra.

## Ele pergunta o meio antes de tentar

```php
$cadeia = new FallbackGateway([
    Gateway::WOOVI->make([...]),   // Pix
    Gateway::REDE->make([...]),    // cartão de crédito
]);

$cadeia->supportedMethods();   // [PIX, CREDIT_CARD] — a união
```

A **união**, e não a interseção, porque `createCharge()` filtra por cobrança:
uma cobrança Pix só considera a Woovi, uma de crédito só considera a Rede.
Sem essa filtragem, uma cobrança Pix seria entregue a um gateway que não faz
Pix — depois de já ter desistido do que fazia.

Quando ninguém da lista faz o meio pedido, a recusa vem **antes de qualquer
HTTP**, nomeando o que cada um faz.

> A união vale para **meios**. Para **capacidades** vale o contrário: o
> decorator declara apenas cobrança e cobrança portável, nunca clientes,
> webhooks ou estornos dos gateways de dentro. Capacidade é perguntada uma vez,
> contra este objeto; meio é escolhido por cobrança.

## Uma cadeia pode ser inútil sem avisar

As regras de payload são **por gateway**, e podem ser incompatíveis:

```php
new FallbackGateway([
    Gateway::ASAAS->make([...]),   // EXIGE vencimento no Pix
    Gateway::WOOVI->make([...]),   // RECUSA vencimento
]);
```

Essa dupla nunca tem os dois como candidatos reais para a mesma cobrança: com
vencimento, a Woovi recusa; sem, o Asaas recusa. O fallback funciona — sempre
cai —, mas a lista não dá a redundância que aparenta. Vale montar a cadeia com
gateways que aceitam o **mesmo** intent.

## Saber o que aconteceu

```php
new FallbackGateway($gateways, function (string $gateway, ?Throwable $falha) {
    logger()->warning('phpay', ['gateway' => $gateway, 'falhou' => $falha?->getMessage()]);
});
```

Chamado depois de cada tentativa: com `null` em quem atendeu, com a exceção em
quem falhou. Sem isso, um primário fora do ar fica invisível.

## O que ele não faz

- **Não cai em leitura** (`find`, `getAll`): a cobrança só existe no gateway
  que a criou. Procurar noutro devolve 404 sempre.
- **Não cai em estorno**: endereçado pelo id do gateway, pela mesma razão.
- **`charge()` é do primário, e não tem fallback.** Ele devolve um recurso com
  estado, e qual gateway atende só se decide na chamada terminal, dentro de um
  objeto que o decorator já não segura. Use `createCharge()`.
- **Não é circuit breaker**: não guarda que um gateway anda falhando. Isso
  precisa de estado entre requisições, e o núcleo é sem estado de propósito.

## Um efeito colateral que vale saber

No Asaas, `createCharge()` cria o cliente antes da cobrança. Se a cobrança
falhar de um jeito que permita cair, o cliente **já foi criado lá** e fica
órfão. É registro sem dinheiro, bem menos grave que cobrança dupla, mas está
aqui para não surpreender.
