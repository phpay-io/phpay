# Testando quem usa a biblioteca

A suíte do PHPay injeta `Client` em cada Resource, e os construtores aceitam
`?Client $client` para isso. Mas **quem consome** a biblioteca não constrói
Resource: recebe o gateway montado, sobretudo
[resolvendo por nome](resolvendo-o-gateway.md).

Para esse caso existe `ClientFactory::fake()`.

[← voltar ao índice](README.md)

## O básico

```php
use PHPay\Http\ClientFactory;

ClientFactory::fake([
    new Response(200, [], json_encode(['id' => 'pay_001', 'status' => 'PENDING'])),
]);

/* o código da aplicação roda igual, sem saber que está em teste */
$pedido->pagar();

ClientFactory::recorded();   // o que ele mandou
ClientFactory::stopFaking();
```

| Membro | |
| --- | --- |
| `fake($respostas)` | daqui em diante, todo client montado responde da fila |
| `faking()` | se está em modo falso |
| `recorded()` | as requisições que a aplicação fez |
| `stopFaking()` | volta ao normal e limpa o histórico |

`fake()` aceita uma lista de respostas ou um `MockHandler` seu, e devolve o
handler — então dá para empilhar mais respostas depois.

## Falseia todos os clients, não um

Isto importa mais do que parece. **Quatro gateways têm mais de um client**:

| Gateway | Clients |
| --- | --- |
| Efí | Cobranças + API Pix |
| Cielo | escritas + consultas |
| Rede | transações + host de OAuth |

Injetar um client só falsearia metade. Na Rede seria pior: ela pede token
**antes de cada chamada**, num host separado, então o teste iria para a rede na
primeira linha.

Como toda a biblioteca monta client em `ClientFactory::make()` — e há teste
varrendo `src/` que reprova qualquer `new Client(` fora dela —, falsear na
fábrica alcança todos de uma vez:

```php
ClientFactory::fake([
    jsonResponse(['access_token' => 'tok', 'token_type' => 'Bearer', 'expires_in' => 3600]),
    jsonResponse(['returnCode' => '00', 'tid' => 'tid_1']),
]);

PHPay::make('rede', $config)->charge()->find('tid_1');   // os dois hosts, falsos
```

## O que ele grava, e o que não grava

Cada entrada tem a `request` e as `options` com que ela saiu. **A resposta não
é gravada**, de propósito: em modo falso as respostas são as que o seu teste
enfileirou, então repeti-las não diz nada. O que o teste não sabe de antemão é
a **requisição**.

```php
$gravado = ClientFactory::recorded();

$gravado[0]['request']->getMethod();                        // 'POST'
(string) $gravado[0]['request']->getUri();                  // .../payments
json_decode((string) $gravado[0]['request']->getBody(), true);
```

O histórico é das requisições que a aplicação **fez**, não de cada tentativa: o
gravador fica por fora do retry. Teste que precise ver tentativa empurra o
próprio gravador, como [retry e idempotência](retry-e-idempotencia.md) descreve.

## Três coisas para não se machucar

**O retry continua ligado.** Falsear a rede não desliga a política que decide o
que pode repetir — teste que queira exercitá-la põe duas respostas na fila.

**É estado estático.** Mesmo contrato do `Mail::fake()` do Laravel: opt-in, e
`stopFaking()` restaura. Chame no setup do teste, e **sempre** desligue no
teardown:

```php
afterEach(fn () => ClientFactory::stopFaking());
```

**Client injetado não é afetado.** O modo falso só muda o que a fábrica produz
daqui em diante; Resource que recebeu `Client` no construtor continua com o
dele. É o que mantém a suíte da própria biblioteca funcionando.
