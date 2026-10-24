# O webhook que chega

Receber notificação tem duas perguntas, e elas falham separadamente: **quem
mandou** e **o que foi dito**. `webhookSignature()` responde a primeira,
`webhookEvents()` a segunda.

Cadastrar o endpoint é metade do caminho. A outra é provar que a notificação
veio do gateway: sem isso, quem descobrir a URL confirma uma cobrança. Cada
gateway assina de um jeito, e `webhookSignature()` dá a todos a mesma forma.

```php
$ok = $gateway->webhookSignature()
    ->setSecret($segredoDoWebhook)
    ->setPayload(file_get_contents('php://input'))
    ->setHeaders(getallheaders())
    ->verify();

if (!$ok) {
    http_response_code(401);
    exit;
}
```

Todo verificador cumpre **`PHPay\Webhooks\VerifiesSignature`**, que carrega os
quatro métodos acima. Isso importa para quem **não** sabe o gateway em tempo de
escrita: [resolvendo por nome](resolvendo-o-gateway.md), `$phpay->webhookSignature()`
vem tipado pelo contrato em vez de `object`, e dá para encadear sem conhecer o
concreto.

> A **capacidade** `SupportsWebhookSignature` continua declarando `object`, e é
> de propósito: estreitá-la seria fatal para quem implementa a capacidade num
> gateway próprio, porque tipo de retorno é covariante. Quem tipa a capacidade
> para injeção de dependência ainda recebe `object` — fechar essa ponta exige
> um major.

Os setters de esquema específico ficam **fora** do contrato, porque pertencem a
um esquema cada: `setDataId()` no Mercado Pago, `setQuery()` e
`setSignatureKey()` na AbacatePay, `setPublicKeys()` na Woovi. Código genérico
pergunta por eles com `method_exists()`.

`verify()` devolve `bool` — assinatura que não bate é resposta, não erro. O que
levanta `ValidationException` é uso errado: segredo ausente, corpo vazio ou
header de assinatura que não chegou.

**O corpo precisa ser o cru.** `file_get_contents('php://input')`, nunca o array
decodificado e reserializado: um espaço a mais muda o hash. Em Laravel,
`$request->getContent()`.

| Gateway | O que é verificado | O que isso prova |
| --- | --- | --- |
| Asaas | token no header `asaas-access-token` | que o remetente conhece o token — o corpo não é assinado |
| PagBank | `x-authenticity-token`: SHA-256 de `{token}-{corpo}` | que o corpo chegou inteiro |
| Mercado Pago | `x-signature`: HMAC-SHA256 de `data.id` + `x-request-id` + `ts` | que a notificação é da sua aplicação e aponta para aquele recurso |
| Woovi/OpenPix | `x-webhook-signature`: RSA-SHA256 do corpo, conferido com a chave pública da Woovi | que o corpo veio da Woovi |
| AbacatePay | `webhookSecret` na query e, com a chave, `X-Webhook-Signature` | que a URL é a sua e, com a chave, que o corpo não mudou |

No Mercado Pago, informe o `data.id` da query com `setDataId()`: ele entra no
cálculo, e omiti-lo muda o manifesto. Na Woovi, a chave pública é buscada em
`api/v1/webhook/public-keys` na primeira verificação — passe `setPublicKeys()`
para evitar a chamada — e a lista inteira é aceita, porque durante uma rotação
ainda chegam notificações assinadas pela chave anterior.

Quem **não** declara a capacidade: **Efí**, porque a notificação Pix chega por
mTLS e o certificado já cobre isso; **Cielo** e **Rede**, porque a notificação
não traz assinatura e o caminho que a própria documentação indica é consultar a
transação de volta; e **Pagar.me**, porque a v5 não documenta esquema de
assinatura. Nos quatro, `webhookSignature()` levanta `NotImplementedException`
em vez de devolver um verificador que sempre diz sim.

---

## Lendo o que a notificação diz

Verificada a origem, o corpo continua no formato daquele gateway. `webhookEvents()`
traduz o envelope:

```php
use PHPay\Webhooks\EventType;

$corpo = file_get_contents('php://input');

if (!$gateway->webhookSignature()->setSecret($segredo)->setPayload($corpo)->setHeaders(getallheaders())->verify()) {
    http_response_code(401);
    exit;
}

foreach ($gateway->webhookEvents($corpo) as $evento) {
    if ($evento->type === EventType::PAID) {
        liberarPedido($evento->resourceId);
    }
}
```

**Verifique a assinatura primeiro, com o corpo cru.** O `webhookEvents()`
aceita a string crua ou o array já decodificado, mas a assinatura só fecha
sobre o cru — reserializar muda o hash.

### Devolve lista, nunca um evento

A API Pix da Efí manda `pix` como **array**: vários pagamentos recebidos numa
requisição só. E a notificação de devolução traz o array `devolucoes` inteiro,
não uma por chamada. O PagBank manda o pedido com todas as `charges`.

Por isso o retorno é `array<Event>` nos nove, mesmo onde hoje sempre vem um.
Tratar como objeto único perderia pagamento em silêncio.

### Quando o gateway não manda o estado

Quatro gateways notificam que *algo* mudou e nada mais. Nesses, `status` é
`null` e `requiresLookup()` é `true`:

```php
foreach ($gateway->webhookEvents($corpo) as $evento) {
    $status = $evento->requiresLookup()
        ? InvoiceStatusEnum::of($phpay->charge()->find($evento->resourceId)['status'])
        : $evento->status;
}
```

Isso **não** é falha da leitura: é o que o gateway manda. Responder
`PaymentStatus::UNKNOWN` ali leria como estado que veio no corpo.

| Gateway | O que chega | `status` |
| --- | --- | :---: |
| Asaas | `event` + o `payment` inteiro | ✅ |
| Woovi/OpenPix | `event` + `charge` e `pix` | ✅ |
| Pagar.me | `type` + `data` (pedido ou cobrança) | ✅ |
| PagBank | o pedido inteiro, **sem campo de evento** — o tipo sai do status | ✅ |
| Efí (Pix) | `pix[]` ou `devolucoes[]`, **sem campo de evento** | ✅ |
| Efí (Cobranças) | um token para trocar pelo detalhe | — |
| Mercado Pago | `type`, `action` e **só** `data.id` | — |
| Cielo | `PaymentId` e `ChangeType`, três campos | — |
| **Rede** | não declarada | — |
| **AbacatePay** | não declarada | — |

Rede e AbacatePay levantam `NotImplementedException`: o formato das duas não
está confirmado, e parser montado sobre forma chutada responderia `UNKNOWN`
para evento real — ou, pior, leria o campo errado como id.

### O tipo e o status respondem perguntas diferentes

`EventType` diz o que **aconteceu**; [`PaymentStatus`](status-de-pagamento.md)
diz o que a cobrança **é**. Um gateway pode mandar o segundo sem nunca mandar o
primeiro — é o caso do PagBank, onde o tipo é inferido do status porque não há
outro sinal.

```php
$evento->type->isPayment();    // o dinheiro entrou nesta notificação
$evento->type->isReversal();   // estorno, parcial ou chargeback
$evento->status?->isFinal();   // nada mais vem depois
```

### Evento desconhecido não levanta

Gateway acrescenta evento sem avisar. `EventType::UNKNOWN` com o nome cru em
`rawType` e o payload intacto é degradação; levantar perderia a notificação,
que o gateway não necessariamente manda de novo.

```php
$evento->type;      // EventType::UNKNOWN
$evento->rawType;   // 'PAYMENT_COISA_NOVA'
$evento->get('payment.campoNovo');
```

Corpo **vazio** ou que não é JSON é outra coisa: é integração que não repassou
o payload, e levanta `ValidationException`.
