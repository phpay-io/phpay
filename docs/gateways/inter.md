# Inter

O primeiro banco da biblioteca, e o primeiro gateway cujo token é emitido
**por escopo**.

A API Pix do Inter é a [especificação do BACEN](https://bacen.github.io/pix-api/)
quase literal: mesmos caminhos, mesmos schemas, mesma taxonomia de erro
(RFC 7807 com `type: https://pix.bcb.gov.br/api/v2/error/...`). Quem já integrou
a API Pix da [Efí](efi.md) reconhece tudo — menos a notificação, que diverge em
dois pontos.

[← voltar ao índice](../README.md)

| | |
| --- | --- |
| Host | `cdpj.partners.bancointer.com.br` |
| Sandbox | `cdpj-sandbox.partners.uatinter.co` — **outro domínio**, não outro caminho |
| Autenticação | OAuth2 `client_credentials` **+ mTLS em toda requisição** |
| Capacidades | `charge()`, `webhook()`, `webhookEvents()` |
| Valores | reais, como `Money` |

```php
use PHPay\Http\Certificate;
use PHPay\Inter\InterGateway;

$gateway = new InterGateway(
    CLIENT_ID,
    CLIENT_SECRET,
    certificate: Certificate::pair('/caminho/certificado.crt', '/caminho/chave.key'),
);
```

## O certificado vem em dois arquivos

E não precisa ser convertido. O Inter entrega um `.crt` e um `.key`, e a
documentação dele manda juntar os dois:

```bash
openssl pkcs12 -export -out inter.pfx -inkey "Inter API_Chave.key" -in "Inter API_Certificado.crt" -aes256
```

O cURL aceita as metades como vêm, então `Certificate::pair()` resolve sem
passo intermediário. Os formatos e a pegadinha da senha estão em
[Certificado e mTLS](../mtls.md).

> **mTLS vale para tudo, inclusive a requisição do token.** Sem certificado, a
> conexão não se estabelece e a credencial nem chega a ser lida — por isso o
> erro, quando ele falta, explica isso em vez de deixar estourar um handshake.

## O token é emitido por escopo, e tem de ser reaproveitado

`scope` é **obrigatório** na requisição do token: não existe um token que
carregue todas as permissões. Cada endpoint documenta o escopo que exige, e o
gateway pede a união do que as capacidades dele usam:

```php
InterGateway::SCOPES;
// 'cob.write cob.read cobv.write cobv.read pix.read pix.write webhook.write webhook.read'
```

Aplicação com permissão mais estreita passa o próprio conjunto — pedir escopo
que a aplicação não tem reprova a requisição **inteira**:

```php
new InterGateway(CLIENT_ID, CLIENT_SECRET, scopes: 'cob.write cob.read');
```

**O cache do token não é otimização aqui.** Gerar token é limitado a **5
chamadas por minuto**, e a documentação do Inter manda reaproveitar um por toda
a hora de validade. O gateway renova sozinho, com margem de 30 segundos, e um
processo que pedisse token por requisição levaria `429` na sexta.

## Cobrança

`charge()` é a **cobrança Pix**. Na Efí `charge()` é o boleto e `pixCharge()` é
o Pix, porque lá as duas APIs já existiam; aqui só a Pix entra, e obrigar
`pixCharge()` num gateway cuja única cobrança é Pix seria cerimônia.

```php
use PHPay\Support\{Customer, Money};

$cobranca = $gateway->charge()
    ->setKey(PIX_KEY)
    ->setAmount(Money::reais('110,00'))
    ->setCustomer(Customer::make('Fulano de Teste', '12345678901'))
    ->setDescription('Pedido 1234')
    ->setExpiration(3600)
    ->create();
```

Sem `txid`, o Inter gera um (`POST /cob`); com `txid`, a cobrança é criada nele
(`PUT /cob/{txid}`). O padrão são 26 a 35 caracteres alfanuméricos.

`setDueDate()` transforma a cobrança em **cobrança com vencimento** (`cobv`),
que exige devedor e sempre tem txid — gerado quando não informado:

```php
$gateway->charge()
    ->setKey(PIX_KEY)
    ->setAmount(Money::reais('50,00'))
    ->setCustomer(Customer::make('Fulano de Teste', '12345678901'))
    ->setDueDate('2026-12-01', validityAfterDue: 15)
    ->create();
```

Expiração e vencimento são exclusivos: o último chamado vence.

> **O valor é `Money`, e só.** A API Pix quer reais como string decimal
> (`"110.00"`). Número cru não é aceito aqui de propósito — ver
> [Valores monetários](../valores-monetarios.md).

**Não existe `qrCode()`.** A Efí tem porque expõe `GET /loc/{id}/qrcode`; o
Inter não expõe essa rota, e o copia-e-cola volta na própria cobrança, em
`pixCopiaECola`.

| | |
| --- | --- |
| `find($txid)` / `findDue($txid)` | consulta imediata / com vencimento |
| `getAll()` / `getAllDue()` | lista — período obrigatório, 30 dias por padrão |
| `update($txid, $dados)` | revisa (`PATCH`) |
| `cancel($txid)` / `cancelDue($txid)` | remove, com `REMOVIDA_PELO_USUARIO_RECEBEDOR` |
| `findPix($e2e)` / `getAllPix()` | Pix recebidos |
| `refund($e2e, $valor, $id?)` | devolução |
| `findRefund($e2e, $id)` | consulta a devolução |

## O estorno não é capacidade aqui

A devolução existe — `PUT /pix/{e2eId}/devolucao/{id}` — mas **o Inter não
declara `SupportsRefunds`**, pela mesma razão da Efí: o schema do BACEN exige
`valor`, enquanto a capacidade promete que valor ausente estorna integral, e a
chamada é endereçada pelo `endToEndId` do pagamento, não pela cobrança.

```php
/* no Resource, com valor obrigatório */
$gateway->charge()->refund($endToEndId, Money::reais('10,00'));

/* pela facade, recusado — e a mensagem diz o que o gateway oferece */
$phpay->refund($txid);   // NotImplementedException
```

## Webhook

Um por chave Pix, endereçado pela chave — não existe id de webhook. Cadastrar
de novo substitui a URL.

```php
$gateway->webhook([
    'chave'      => PIX_KEY,
    'webhookUrl' => 'https://app.exemplo.com/webhook/',
])->create();
```

> **A notificação chega na URL que você cadastrou**, e a biblioteca manda a URL
> exatamente como você passou.
>
> Vale dizer porque a referência convida à leitura oposta: o Redoc mostra o
> callback como `POST {$request.body#/webhookUrl/pix}`, o que parece um `/pix`
> acrescentado ao final. Não é. O `#/` introduz um **JSON Pointer**, e esse
> apontaria para uma propriedade `pix` dentro de uma string; o que houve é que
> o callback se **chama** `pix` na especificação, e o Redoc juntou o nome à
> expressão. O texto do Inter descreve um `POST` na `webhookUrl` cadastrada, e
> o exemplo dele não tem sufixo.
>
> O sufixo `/pix` existe — é quirk documentado do **Itaú**, que implementa a
> mesma especificação. Não confunda os dois.

Só `https` é aceito. **Não existe listagem** de webhooks: o Inter expõe os
endpoints por chave e o log de entregas, e nada mais. A taxonomia de erros da
referência menciona um `GET /webhook`, mas aquela seção é o texto do BACEN
copiado inteiro e nomeia endpoints que este gateway não serve.

| | |
| --- | --- |
| `find($chave)` | o webhook daquela chave |
| `destroy($chave)` | remove |
| `callbacks()` | log de entregas: `sucesso`, `httpStatus`, `mensagemErro`, `numeroTentativa` |
| `retryCallbacks($chave, $txids)` | pede reenvio, até 50 txid por chamada |

O `callbacks()` é extra do recurso concreto, não capacidade — ele lê o log de
entrega, a mesma coisa que o
[`webhookDeliveries()` do Pagar.me](pagarme.md#consultando-entregas-de-webhook).
É como se descobre que a notificação saiu e o seu servidor respondeu 500. O
Inter tenta de novo quatro vezes por conta própria, em 20, 30, 60 e 120
minutos; depois disso, só por `retryCallbacks()`.

## Lendo a notificação: dois desvios do padrão

`webhookEvents()` cuida dos dois, e os dois são do tipo que **falha em
silêncio**.

**O corpo é um array na raiz.** Não é `{"pix": [...]}` como o BACEN manda e como
a Efí entrega — o array é o corpo:

```json
[
  {
    "endToEndId": "E87654321202009091221dfghi123456",
    "chave": "a82d0acf-6cad-42a1-b5d4-4165416545646845",
    "componentesValor": { "original": { "valor": 100 } },
    "txid": "971122d8f37211eaadc10242ac120002",
    "valor": "110.00",
    "horario": "2020-09-09T20:15:00.358Z",
    "infoPagador": "0123456789"
  }
]
```

Um parser escrito para o envelope do padrão não acharia a chave `pix` e
devolveria **zero eventos** para todo pagamento de verdade. O envelope `pix`
também é aceito, porque a seção "Evolução da API Pix" da referência reserva o
direito de acrescentar campos a qualquer momento.

**`devolucoes` é um objeto, não um array.** No BACEN e na Efí é array. Pode ser
erro de documentação do Inter, então as duas formas são lidas: lista continua
lista, objeto vira lista de um.

```php
foreach ($gateway->webhookEvents($corpoCru) as $evento) {
    $evento->type;        // EventType::PAID, EventType::REFUNDED
    $evento->resourceId;  // o txid, ou o endToEndId quando não vem txid
    $evento->status;      // PaymentStatus::PAID — o corpo traz o pagamento
}
```

Não existe nome de evento no payload, como na API Pix da Efí, então o tipo vem
do que a entrada carrega. Isso não é inferência: uma entrada com valor,
`endToEndId` e horário de recebimento **é** o pagamento.

Devolução só reporta estado quando o `status` é `DEVOLVIDO`;
`EM_PROCESSAMENTO` e `NAO_REALIZADO` ficam sem estado, e `requiresLookup()`
manda consultar — ler uma devolução em andamento como concluída liberaria o
pedido duas vezes. O tipo é sempre `REFUNDED` e nunca `PARTIALLY_REFUNDED`:
distinguir exigiria comparar o valor devolvido com o da cobrança, e os dois
campos de valor do exemplo do próprio Inter discordam em tipo e em ordem de
grandeza.

## Assinatura de webhook: não tem

E não é omissão. A notificação é autenticada por **mTLS**, que o certificado já
cobre — não há esquema de assinatura para verificar, igual à Efí. O motivo de
cada gateway está em [Verificando o webhook que chega](../webhooks.md).

## Mais de uma conta corrente

O header `x-conta-corrente` identifica em qual conta operar, e o Inter só o
exige quando a aplicação está vinculada a mais de uma:

```php
new InterGateway(CLIENT_ID, CLIENT_SECRET, account: '123456');
```

Sem valor, o header não é mandado — obrigar toda integração a saber um número
de conta que ela não precisa seria ruído.

## O que ficou de fora

| | |
| --- | --- |
| **Boleto** (`/cobranca/v3`) | outra API, com host, prefixo e escopos próprios. É assíncrona: `POST /cobrancas` devolve um `codigoSolicitacao` e o resto vem por consulta ou webhook, o que merece desenho próprio |
| **Lote de cobrança** (`lotecobv`) | sem equivalente no modelo de capacidades |
| **Location do payload** (`loc`) | idem — e o copia-e-cola já vem na cobrança |
| **Pix Automático** | API separada, como na Efí |
| **`POST /cob/pagar/{txid}`** | pagar uma cobrança; a própria referência marca como **só sandbox** |
| **Chaves Pix** | a API não tem endpoint de DICT: o webhook é endereçado por uma chave que você já tem, e não há como criar, listar ou remover |
