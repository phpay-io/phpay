# Retry e idempotência

A biblioteca repete uma requisição **só quando repetir não pode cobrar de novo**.
Vale para os nove gateways, porque o middleware vive na
`PHPay\Http\ClientFactory`, o único lugar onde um client nasce.

| Situação | Repete |
| --- | --- |
| Erro antes de a requisição sair (DNS, connect, handshake TLS) | sempre — nada chegou ao gateway |
| Timeout sem conexão estabelecida | sempre — nada chegou |
| Timeout com conexão estabelecida | só leitura, ou com chave de idempotência |
| `429` | sempre, respeitando `Retry-After` |
| `5xx` | só leitura, ou com chave de idempotência |
| `4xx` fora do 429 | nunca — o gateway entendeu e recusou |

Leitura é `GET`, `HEAD` e `OPTIONS`. `PUT`, `PATCH` e `DELETE` **não** são
repetidos, apesar de o HTTP chamar `PUT` e `DELETE` de idempotentes: a
documentação do Asaas pede para
[não tratar a exclusão de cobrança como idempotente](https://docs.asaas.com/reference/excluir-cobranca)
sem conferir a resposta, e esse aviso vale mais que a teoria.

Funciona igual no Guzzle 7 e no 8, apesar de as duas majors responderem de formas
diferentes à pergunta "a requisição chegou a sair?". O 7 joga tudo numa
`ConnectException` e só o `errno` do cURL separa os casos; o 8 removeu esse acessor
e separou em classes. A política pergunta na língua de cada uma — você não precisa
saber qual está instalada.

São 3 tentativas no total — 200ms, 400ms, teto de 2s, com jitter. Se o gateway
mandar um `Retry-After` maior que o teto, a biblioteca **desiste** em vez de
repetir antes da janela pedida, porque repetir cedo só seria recusado de novo.
E lembre que retry multiplica o pior caso de tempo de parede: 3 tentativas com
`timeout` de 60s é o limite superior.

Para mudar a política, passe uma à fábrica:

```php
use PHPay\Http\{ClientFactory, RetryPolicy};

$maisTentativas = ClientFactory::make(['base_uri' => $uri], new RetryPolicy(5));
$semRetry       = ClientFactory::make(['base_uri' => $uri], RetryPolicy::disabled());
```

## Chave de idempotência

Com uma chave, o `POST` passa a ser repetível: o gateway reconhece a segunda
requisição como a mesma operação e devolve o resultado da primeira em vez de
cobrar outra vez.

| Gateway | Header | Como |
| --- | --- | --- |
| Mercado Pago | `X-Idempotency-Key` | sempre enviada — a API de pagamentos exige |
| Pagar.me | `Idempotency-Key` | você informa; vale 24h |
| PagBank | `x-idempotency-key` | você informa; vale 48h |

```php
$pedido = $pagbank->charge()
    ->setCustomer($cliente)
    ->addItem('Assinatura PHPay', 10050)
    ->setIdempotencyKey($idDoSeuPedido)
    ->create();
```

**Use uma chave sua, guardada junto do registro pendente.** Chave gerada pela
biblioteca cobre só as tentativas de dentro da mesma chamada — o retry reenvia
a mesma requisição, com o mesmo header. O retry do seu lado, em outro processo
ou em outra chamada a `create()`, chega com chave nova e cobra de novo.

Os outros gateways não documentam chave de idempotência. Neles um `POST` só é
repetido quando o erro provou que a requisição não saiu da máquina — e no Asaas
a orientação da própria documentação é
[consultar a cobrança antes de repetir](https://docs.asaas.com/docs/cobrancas-via-cartao-de-credito).
