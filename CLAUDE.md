# CLAUDE.md

Orientações para o Claude Code trabalhar neste repositório.

## O que é

PHPay (`phpay-io/phpay`) é uma **biblioteca PHP** (não uma aplicação) que padroniza a
integração com gateways de pagamento brasileiros. Hoje suporta **Asaas** e
**Woovi/OpenPix** (as sete capacidades), **Efí** (todas menos clientes e
estornos), **Mercado Pago**, **PagBank** e **Pagar.me** (clientes, cobranças,
estornos, assinaturas), **Cielo** (cobranças, estornos e recorrência),
**AbacatePay** (clientes e cobranças), **Rede** (cobranças e estornos) e
**Inter** (cobranças, webhooks e leitura de webhook).

Requisitos: PHP `^8.1` para consumir a lib; `^8.2` para rodar o ambiente de dev
(Pest 3 e Termwind 2 exigem 8.2+). Dependências de runtime: `ext-curl`,
`ext-json`, `ext-openssl` (a assinatura de webhook da Woovi é RSA) e
`guzzlehttp/guzzle ^7.3` (a 7.3 é a primeira que entrega `.p12` ao cURL pela
extensão, e o mTLS depende disso). Publicado no Packagist.

## Arquitetura

```
PHPay (facade)  ──implements──▶  PHPay\Contracts\GatewayInterface
   │ delega tudo para o gateway injetado no construtor
   ▼
AsaasGateway / EfiGateway  ──implements──▶  <Gateway>Interface extends GatewayInterface
   │ cada método (customer/charge/pix/webhook/subscription) devolve um Resource novo
   ▼
Resources (Customer, Charge, Pix, Webhook, Subscription)
   │ trait HasAsaasClient / HasEfiClient  →  PHPay\Http\HasHttpClient (get/post/put/patch/delete/download)
   ▼
Requests (validação estática dos payloads antes de qualquer chamada HTTP)
```

Regras que valem para todo código novo:

- **Capacidade, não contrato único.** `GatewayInterface` carrega só `name()`. Cada
  recurso é uma interface em `src/Contracts/` (`SupportsCustomers`, `SupportsCharges`,
  `SupportsRefunds`, `SupportsWebhooks`, `SupportsPixKeys`, `SupportsSubscriptions`,
  `SupportsWebhookSignature`, `SupportsWebhookEvents`) que o gateway
  implementa se — e só se — oferecer aquele recurso. **Nunca** declare um recurso para
  depois lançar de dentro dele.
- **Recurso novo = interface nova + case na enum `Capability` + método guardado na
  facade.** O guard é `instanceof` seguido de
  `NotImplementedException::forCapability()`; o PHPStan usa esse `instanceof` para
  estreitar o tipo, então não troque por um helper genérico.
- **`SupportsRefunds` e `SupportsWebhookEvents` declaram operação, não getter de
  recurso**, e a assimetria é de propósito: o que divergia entre os gateways não era
  como chegar à cobrança, era a chamada — três nomes (`refund`, `cancel`, `void`),
  três unidades e três sentidos para valor ausente. Devolver recurso deixaria quem
  chama adivinhando o nome do método por gateway, que é a divergência que a
  capacidade fecha. Ela estende `SupportsCharges`, o `refund()` do gateway delega ao
  `Charge`, e a assinatura é `refund(string $id, Money|int|null $amount = null)` —
  `Money|int|float|null` no Asaas e no Mercado Pago, que são de reais. `null` estorna
  integral em todos. **Estorno não é repetido pelo retry**: é escrita que move
  dinheiro, e repetir devolve duas vezes. Onde o gateway já tinha `cancel()` (Cielo,
  Pagar.me) o nome antigo fica, porque ali um endpoint só anula e estorna; o
  `refund()` é o nome portável sobre o mesmo endpoint.
- **A facade `PHPay` não conhece gateway concreto**, por isso ela checa em runtime.
  Quem segura o gateway concreto ganha a checagem em tempo de análise.
- **Cada Resource tem uma Interface própria** em `Resources/<Nome>/Interface/`.
- **Resource é descartável e carrega estado via setters fluentes** (`setCharge`,
  `setCustomer`, `setCustomerId`, `setQueryParams`, `setFilter`), sempre com `return $this`
  tipado pela interface, e um método terminal (`create`, `getAll`, `find`, …).
- **Todo Resource aceita `?Client $client = null` como último parâmetro do construtor**
  e faz `$this->client = $client ?? $this->client<Gateway>Boot()`. É isso que torna a
  suíte testável sem rede — não crie Resource sem esse parâmetro.
- **O `<gateway>Boot()` monta o client com `PHPay\Http\ClientFactory::make()`**, nunca
  com `new Client(`. A fábrica põe `timeout` de 60s e `connect_timeout` de 10s, porque o
  padrão do Guzzle é esperar para sempre, e instala o middleware da `RetryPolicy`. Um
  teste varre `src/` e reprova `new Client(` fora dela.
- **Validação de payload vive em classes `*Request` estáticas**, com o par
  `validate(array): void` + `messages(): object`. As mensagens são em **português** e
  **não** carregam o prefixo do gateway: quem prefixa é
  `ValidationException::make('<Gateway>', $mensagem)`.
- **`messages()` precisa de object shape no PHPDoc** (`@return object{campo: string, ...}`),
  senão o PHPStan nível 9 acusa `property.notFound`.
- O trait do gateway expõe `baseUri()` (sandbox/produção) e `gatewayName()`. As URLs são
  métodos, **não constantes**: constante em trait só existe a partir do PHP 8.2 e a lib
  suporta 8.1 — o `phpVersion` do `phpstan.neon` reprova isso.

## Erros

Regra central: **um array retornado é sempre resposta de sucesso.** Qualquer falha vira
exceção, e todas implementam `PHPay\Exceptions\PHPayException`:

| Exceção | Estende | Quando |
| --- | --- | --- |
| `ValidationException` | `InvalidArgumentException` | payload inválido, antes de qualquer HTTP |
| `ApiException` | `RuntimeException` | gateway recusou ou está inacessível |
| `NotImplementedException` | `BadMethodCallException` | recurso não suportado pelo gateway |

`ApiException` carrega `getStatusCode()`, `getResponse()`, `getGateway()` e
`isConnectionError()` (status `0` = nem chegou ao gateway). Construa sempre via
`ApiException::fromThrowable()`, que já resume os formatos de erro do Asaas
(`errors[].description`) e da Efí (`error_description`).

## Comandos

Rodar direto no host (requer PHP 8.2+ e Composer):

```bash
composer install
composer test        # lint + unit + types + links — mesmo gate do CI
composer test:lint   # pint --test (PSR-12 + regras do pint.json)
composer test:unit   # pest
composer test:types  # phpstan (nível e phpVersion vêm do phpstan.neon)
composer test:links  # links relativos e âncoras dos markdowns (.github/scripts/check-links.php)
composer lint        # pint -v (corrige)
```

Ou via Docker (`make help` lista tudo):

```bash
make start && make install && make test
```

Rodar um subconjunto:

```bash
./vendor/bin/pest --filter="reaproveita o cliente"
./vendor/bin/pest --group=asaas   # phpay, support, ou o nome do gateway
```

Exemplos manuais contra o sandbox (`examples/`): copie
`examples/<gateway>/credentials.example.php` para `credentials.php`, preencha as constantes
e rode `php examples/asaas/charges.php` (ou `make asaas resource=charges`).
**`credentials.php` não é versionado — nunca commitar token real.**

## Testes

- Nenhum teste toca a rede. `tests/Pest.php` expõe `mockClient(array $responses, array &$history)`,
  `jsonResponse(array $data, int $status)` e `recordedBody(array $history, int $index)`.
- O padrão é: montar o Resource com o client mockado, executar, e **asseverar sobre o
  histórico de requisições** (método, URI, corpo) além do retorno.
- Todo teste declara um grupo: `phpay` (núcleo), `support` (`Money`, `Customer`) ou o do
  gateway (`asaas`, `efi`, `woovi`, `mercadopago`, `pagbank`, `pagarme`, `cielo`, `rede`,
  `abacatepay`).
- O PHPStan analisa só `src` — o Pest usa `__call` para `group()`/`with()` e o nível 9
  não consegue resolver isso em `tests`.

## Convenções obrigatórias

- **Estilo:** PSR-12 via Pint, com alinhamento de `=` e `=>` (`align_single_space_minimal`).
  Rode `composer lint` antes de commitar; o hook `pre-commit` roda `pint --test` + phpstan.
- **PHPDoc em todo método**, incluindo `@param array<mixed>` / `@return array<mixed>` —
  o PHPStan roda em nível 9 e arrays sem generics quebram o build. Documente também o
  `@throws`.
- **Nada de `(string) $valorMixed`.** Nível 9 reprova cast de `mixed`; use `is_string()`
  antes ou lance exceção.
- **Mensagens de commit:** o hook `commit-msg` exige que a branch contenha um número de
  issue e prefixa automaticamente `PHPAY-<id>: `. Formato do corpo: `tipo(escopo): descrição`
  (`feat`, `fix`, `refactor`, `doc`, `wip`, `test`). Branch base de PR: **`develop`**.
  **Assunto e corpo em inglês**, como os comentários e o PHPDoc do `src/`; em português
  ficam só as mensagens de validação, a documentação (README, CLAUDE.md, UPGRADE.md), as
  issues e o corpo do PR.
  **Nunca adicione linhas de co-autoria ou atribuição em commits e PRs.**
- **Hooks Husky:** `pre-commit` (pint + phpstan), `pre-push` (pest), `commit-msg`.
  Instalados por `npm install`.
- **`PHPay\PHPay` colide com qualquer alias de caixa diferente.** Nomes de classe em
  PHP são **insensíveis a caixa**, então num arquivo com `use PHPay\PHPay;` o nome
  `PHPay` é alias da classe — e FQN escrito inline começando em `PHPay\` resolve sob
  ele (`PHPay\Exceptions\X` vira `PHPay\PHPay\Exceptions\X`, que não existe).
  **Importe a classe** em vez de escrever o FQN inline; em teste, com alias quando o
  nome curto colidir (`Charge as AsaasCharge`). O erro aparece como
  `UnknownClassOrInterfaceException` ou `Cannot redeclare class`, e não é óbvio pelo
  que a mensagem diz.
- **Namespaces:** `PHPay\` → `src/`, e um root por gateway: `PHPay\Asaas\`,
  `PHPay\Efi\`, `PHPay\MercadoPago\` → `src/Gateways/<Gateway>/`. Gateway novo
  precisa de um root novo no `composer.json` — não introduza `PHPay\Gateways\...`.

## Resolvendo o gateway

Dois caminhos, e **`PHPay::gateway()` continua sendo o preferido**: segurando o gateway
concreto, capacidade ausente é erro de análise e não de runtime. O registry é para quem
não sabe o gateway em tempo de escrita.

- **`PHPay\Support\Gateway`** é o enum com os dez, e é **o único lugar do núcleo que
  conhece gateway concreto** — é o que uma fábrica é. A propriedade que importa segue
  valendo: a facade despacha por capacidade e nunca nomeia gateway concreto no caminho
  de despacho.
- `PHPay::make('asaas', $config)` devolve a facade; `Gateway::ASAAS->make($config)`
  devolve o gateway concreto. O enum sabe `credentials()`, `options()`,
  `supportsSandbox()` e `gatewayClass()`.
- **Os construtores não concordam, e o registry respeita isso**: seis gateways pedem
  uma credencial e três pedem duas; **Mercado Pago, Pagar.me e AbacatePay não aceitam
  `sandbox`**, porque o ambiente deles vem do prefixo da credencial (ou, na AbacatePay,
  de nada). Mandar `sandbox` para esses três **levanta** em vez de ser ignorado: a
  chave revela um mal-entendido sobre como o ambiente é escolhido.
- **Chave desconhecida também levanta.** Mesma razão da forma fechada de array do
  `ClientFactory`: `skandbox` engolido é typo que só aparece em produção. A validação
  mora em `GatewayConfigRequest`, no par `validate()` + `messages()` como os outros.
- **Nome errado levanta `ValidationException` listando os dez**, não `ValueError` do
  enum — o nome costuma vir de `.env`, e quem errou merece a lista. `tryFrom()` segue
  disponível para quem quer `null`.
- **O registry não recebe `Client`.** Client injetado é de teste, e teste constrói
  direto. Gateway novo precisa de case no enum, braço no `make()` e entrada em
  `credentials()` — há teste que varre `Gateway::cases()` e outro que compara a
  contagem com `src/Gateways/*`, então esquecer qualquer um reprova.

## Cliente

Use **`PHPay\Support\Customer`**. O mesmo campo tem seis grafias entre os gateways
(`cpfCnpj`, `tax_id`, `document`, `taxId`, `taxID`, `cpf_cnpj`), e o VO é a forma única.

- `Customer::make()` limpa pontuação de documento e telefone; o construtor não.
- **O mapeamento mora na classe `*CustomerRequest` de cada gateway**, em
  `fromCustomer(Customer): array` — ela já detém o conhecimento do schema daquele
  gateway, então validação e mapeamento ficam juntos. Gateway novo com cliente deve
  ter o mesmo método.
- Todo `setCustomer()` e `customer()` aceita `Customer|array`. Array continua
  funcionando; não remova esse caminho sem major.
- Lógica derivada mora no VO, não nos gateways: `isIndividual()`, `documentType()`,
  `firstName()`/`lastName()`, `phoneParts()`. Se um gateway novo precisar de outra
  derivação, acrescente lá em vez de calcular no mapper.
- `withExtra()` é para campo específico de um gateway. **Nenhum campo obrigatório
  precisa dele hoje** — se um gateway novo precisar, é sinal de que o VO está
  faltando algo.

## Valores monetários

Use **`PHPay\Support\Money`**. Os gateways discordam da unidade — Asaas e Mercado
Pago querem reais decimais, os outros sete querem centavos inteiros — e errar não
quebra a integração, cobra o valor errado.

- Fábricas: `Money::reais()` (aceita float, int e string em notação brasileira) e
  `Money::centavos()`. Acessores na instância: `toReais()` e `toCentavos()`.
- **Dentro dos gateways**, normalize com `Money::asReais()` ou `Money::asCentavos()`,
  que aceitam `Money` ou número cru. Nunca leia o valor direto do parâmetro.
- Todo método que recebe valor aceita `Money|int` (ou `Money|int|float` nos de reais).
  Número cru é lido na unidade que aquele gateway sempre esperou — compatibilidade.
- Onde o valor vive dentro do array de payload (Asaas, Mercado Pago, Efí), existe
  `setAmount()`. Gateway novo com valor em array deve ter o mesmo.
- `Money::reais()` **recusa mais de duas casas decimais** de propósito. Não "conserte"
  isso com arredondamento: é o que impede erro de um centavo na conciliação.

## Status de pagamento

Use **`PHPay\Support\PaymentStatus`**. É o `Money` do outro lado da chamada: dez
vocabulários para os mesmos estados — `RECEIVED`, `COMPLETED`, `approved`, `PAID`,
`paid`, `CONCLUIDA`, `'00'` — e quem troca de gateway reescreve todo `if`.

- Suporte, não capacidade. Não entra na enum `Capability`: não existe gateway que
  deixe de reportar status, então não há o que declarar. Fica ao lado do `Money` e do
  `StaticPixCode`.
- **O enum concreto de cada gateway fica**, e ganha dois métodos: `status()` traduz um
  case, e `of(?string $raw)` lê o valor cru da resposta tolerando o que não conhece.
  Quem precisa do estado específico daquele gateway continua com ele na mão. Gateway
  novo precisa do mesmo par.
- **`UNKNOWN` não é preguiça.** Gateway acrescenta status sem avisar, e a alternativa
  é levantar exceção ao ler uma resposta de **sucesso**, o que contraria a regra
  central do modelo de erros. Ler estado novo como desconhecido degrada; quebrar perde
  a cobrança. Um teste varre todos os cases de todos os enums e reprova se algum
  mapear para `UNKNOWN` — ali seria buraco no `match`, não degradação.
- **`PAID` não é final**, de propósito: estorno e chargeback ainda vêm depois, e
  tratar recebido como encerrado é como um estorno passa despercebido. Finais são
  `REFUNDED`, `CHARGEBACK`, `CANCELED`, `EXPIRED` e `FAILED`. Lógica derivada mora no
  VO (`isPaid()`, `isRefunded()`, `isFinal()`), não nos gateways.
- **`AUTHORIZED` existe porque Cielo, PagBank e Mercado Pago separam autorização de
  captura.** Sem ele, cobrança autorizada e não capturada leria como paga, e isso é
  dinheiro que ainda não entrou. `PARTIALLY_PAID` existe pelo `underpaid` do Pagar.me,
  pelo mesmo motivo: ler pagamento a menos como pagamento entrega produto por menos do
  que foi vendido.
- **Duas exceções que não se generalizam.** Na **Rede**, código não listado é `FAILED`
  e não `UNKNOWN`: o enum lista só `'00'` e `'220'` porque os outros são dezenas de
  motivos de recusa, então responder "não sei" esconderia uma recusa (código ausente
  continua `UNKNOWN`, que é campo faltando). No **Pagar.me**, `canceled` cobre
  cancelamento e estorno — a API usa um `DELETE` só —, então estorno lá lê como
  `CANCELED`; é limite do gateway, e está no PHPDoc para ninguém "consertar".
- A **Efí tem dois enums** (`ChargeStatusEnum` para Cobranças, `PixChargeStatusEnum`
  para a API Pix), porque são duas APIs com vocabulários diferentes e as mesmas
  credenciais. Ler uma com o enum da outra devolve `UNKNOWN`, e há teste exigindo isso.
- **Status de assinatura é outro ciclo de vida** e fica fora: os dois
  `SubscriptionStatusEnum` (Mercado Pago, PagBank) descrevem ativa/pausada/cancelada,
  que não se mapeia nestes treze casos. Merece VO próprio se alguém precisar.

## Falseando a rede para quem usa a lib

Use **`ClientFactory::fake()`**. A suíte daqui injeta `Client` nos Resources, mas quem
**consome** não constrói Resource — recebe o gateway montado, sobretudo resolvendo por
nome —, e o registry não aceita `Client`.

- **Falseia na fábrica, e é por isso que funciona.** Toda a biblioteca monta client em
  `ClientFactory::make()` (14 pontos, zero `new Client(` fora dela, com teste varrendo
  `src/`), então falsear ali alcança **todos** os clients de uma vez.
- **Aceitar `Client` no registry foi considerado e descartado**: Efí, Cielo e Rede têm
  **dois clients cada** (Pix, consultas, OAuth). Um client só falsearia metade — e na
  Rede, que pede token antes de cada chamada num host separado, a outra metade iria
  para a rede na primeira linha do teste.
- **O retry continua instalado** em modo falso: falsear a rede não é desligar a
  política que impede cobrança dupla. Teste que queira exercitá-la põe duas respostas
  na fila.
- **O gravador é próprio, não o `Middleware::history` do Guzzle**, e não por capricho:
  o container daquele middleware é por referência e tipado `array|ArrayAccess<int,
  array>`, cujo template é invariante — **nenhuma** propriedade tipada com precisão
  satisfaz isso no nível 9. Escrever dez linhas aqui mantém o tipo e evita supressão.
- **A resposta não é gravada**, só `request` e `options`: em modo falso as respostas
  são as que o teste enfileirou. O que o teste não sabe é a requisição.
- **É estado estático**, com o contrato do `Mail::fake()`: opt-in e `stopFaking()`
  restaura. Todo teste que chama `fake()` desliga no teardown, senão vaza para o
  próximo — a suíte inteira passa justamente porque isso é respeitado.
- **Client injetado não passa pela fábrica**, então não é falseado. É o que mantém a
  suíte da própria biblioteca funcionando com `fake()` ligado, e há teste afirmando.

## Retry e idempotência

A `PHPay\Http\RetryPolicy` decide o que pode ser repetido, e o middleware entra na
`ClientFactory` — então os dez gateways herdam a política de uma vez.

- **Repetir é sobre dinheiro, não sobre resiliência.** Um `POST` repetido que o
  gateway já processou cobra o cliente duas vezes. Só é repetido: leitura
  (`GET`/`HEAD`/`OPTIONS`), erro que provou não ter saído da máquina, `429`, e
  escrita com chave de idempotência.
- **No Guzzle 7, `ConnectException` não significa "não chegou".** O `CurlFactory`
  mapeia `CURLE_OPERATION_TIMEOUTED` e `CURLE_GOT_NOTHING` para a mesma exceção dos
  erros de conexão. Lá a política lê o `errno` do `getHandlerContext()`: `5`, `6`, `7`
  e `35` nunca saíram; `28` só é seguro com `connect_time` zerado; `52` é tratado como
  enviado. **Não simplifique isso para `instanceof ConnectException` no 7** — é o
  caminho direto para a cobrança duplicada.
- **No Guzzle 8 é o contrário**: ele removeu o `getHandlerContext()` e separou os
  casos em classes, e a própria migração manda classificar por classe e não por
  código. Lá `ConnectException` (e a `ConnectTimeoutException`, que herda dela)
  significa que a conexão não se estabeleceu — nada saiu; `NetworkException`,
  `NetworkTimeoutException` e a família `Response*` aconteceram com a requisição já
  na linha. A política detecta a major pelo `method_exists` do acessor, e o gatilho
  comum às duas é o `NetworkExceptionInterface` do PSR-18. **Nenhum dos caminhos
  nomeia classe que só existe numa major**, senão o PHPStan quebraria na outra.
- A mesma regra vale para **tipo**, não só para classe, e o nível 9 roda nas duas
  majors (o job do Guzzle 8 analisa também). A 8 declara o que a 7 deixava solto:
  `Client::__construct` e `Client::request()` recebem **forma de array fechada**,
  `HandlerStack` ficou **genérica** (`HandlerStack<THandler>`) e `Middleware::retry`
  declara a assinatura exata do decider. Daí três hábitos:
  **(1)** o que se passa ao Guzzle é descrito por forma de array com as opções que a
  lib de fato usa — `array<string, mixed>` não satisfaz forma fechada, e a forma
  estreita ainda transforma nome de opção errado em erro aqui em vez de chave que o
  Guzzle ignora calado. A de `request()` é o alias `RequestOptions` do
  `HasHttpClient`, importado com `@phpstan-import-type` por quem monta opções em
  variável (hoje só o `authorized()` da Rede);
  **(2)** **não se declara `HandlerStack` como tipo de retorno nem dentro de forma de
  array** — com argumento genérico quebra na 7, sem ele quebra na 8. Por isso a pilha
  é montada dentro do `ClientFactory::make()`, como variável local, onde o tipo é
  inferido; e `handler` entra na forma como `callable`, que é o que uma `HandlerStack`
  também é;
  **(3)** callable entregue ao Guzzle **aceita o que a major mais larga passa** e
  estreita de volta na entrada: o quarto argumento do decider é `mixed`, não
  `?Throwable`, e vira `Throwable|null` antes de chegar ao `shouldRetry()`.
  Nada disso se resolve com `ignoreErrors` nem baixando o nível — os dois apagariam
  erro de verdade mais adiante.
- Teste de cenário que só existe numa major leva `->skip()` com o motivo — um
  timeout com conexão aberta é `ConnectException` no 7 e `NetworkTimeoutException`
  no 8, então o mesmo teste não pode valer nas duas.
- **`PUT`, `PATCH` e `DELETE` não são repetidos**, apesar do HTTP. A doc do Asaas
  pede para não tratar a exclusão de cobrança como idempotente sem conferir a
  resposta.
- **`Retry-After` maior que o teto faz desistir**, em vez de repetir antes da janela
  pedida.
- O middleware é empurrado **por último** na pilha, o que o deixa mais interno que o
  `http_errors`: ele vê o 5xx como resposta, antes de virar exceção. Teste que precise
  ver cada tentativa tem que empurrar o gravador **depois** da fábrica, senão fica
  fora do retry e só vê a chamada externa.
- Client injetado em teste não passa pela fábrica, então não tem retry. É de
  propósito: a suíte não espera.

Chave de idempotência, só onde o gateway documenta: Mercado Pago
(`X-Idempotency-Key`, sempre enviada, a API exige), Pagar.me (`Idempotency-Key`, 24h)
e PagBank (`x-idempotency-key`, 48h). Nos dois últimos é **opt-in** por
`setIdempotencyKey()`: no PagBank o OpenAPI de criar pedido não lista o header, e
mandar chave em toda requisição esbarraria no `409 idempotency_key_in_use`. Gateway
novo só ganha setter se a documentação dele descrever o mecanismo — e a geração da
chave vem de `PHPay\Http\Idempotency::key()`, não de `random_bytes` espalhado.

## QR Code estático

Use **`PHPay\Support\StaticPixCode`**. Pelo padrão do BACEN um QR Code estático é
composto localmente a partir da chave, então não é Resource, não é capacidade e não
entra na enum `Capability` — é suporte, como o `Money`. O Asaas e a Woovi expõem
endpoint por conveniência; a Efí não expõe, e isso não é falta dela.

- **O CRC cobre o literal `6304`**, não para antes dele. Polinômio `0x1021`, C.I.
  `0xFFFF`. É o erro mais comum de quem implementa o padrão, e gera um código que
  não lê — o vetor oficial do manual do BACEN está na suíte exigindo o payload byte
  a byte, e **não é teste opcional**: para o mesmo vetor, o cálculo errado devolve
  `4AAD` em vez de `1D3D`.
- O tamanho no TLV é em **bytes**, não em caracteres: quem lê o payload anda em
  bytes, e um nome com acento desalinharia todos os campos seguintes.
- O objeto é imutável, como o `Money` e o `Customer`: `with...()` devolve outro.
- **Só o payload**, nunca a imagem: renderizar exige codificador de QR Code, e isso
  seria dependência nova numa biblioteca de pagamentos.
- O `merchantName` é exigido pelo padrão e **ignorado pelo app do pagador**, que
  mostra o nome vindo do DICT. Está no PHPDoc para ninguém construir em cima dele.
- Não se valida o formato da chave — EVP, e-mail, telefone e CPF/CNPJ não se
  parecem, e errar reprovaria chave boa.

## Leitura de webhook

Use **`webhookEvents()`**. Receber notificação tem duas perguntas que falham
separadamente: **quem mandou** (`SupportsWebhookSignature`) e **o que foi dito**
(`SupportsWebhookEvents`). Um gateway pode assinar sem a lib conhecer o payload, e
o contrário.

- **Devolve `array<Event>`, nunca um evento.** A API Pix da Efí manda `pix` como
  array — vários pagamentos numa requisição — e a notificação de devolução traz o
  array `devolucoes` inteiro; o PagBank manda o pedido com todas as `charges`.
  Tratar como objeto único perde pagamento em silêncio, e por isso o retorno é lista
  nos sete, mesmo onde hoje sempre vem um.
- **`status` é anulável, e `requiresLookup()` é a pergunta.** Mercado Pago, Cielo e a
  API de Cobranças da Efí notificam que algo mudou e nada mais. Devolver
  `PaymentStatus::UNKNOWN` ali leria como estado que veio no corpo, então `status`
  fica `null` e `requiresLookup()` diz que é preciso consultar. **Não se infere
  status de nome de evento** — `type` diz o que aconteceu, `status` só o que o
  gateway mandou num campo de estado. A exceção é a Efí: o `pix[]` traz valor,
  `endToEndId` e horário de recebimento, e isso **é** o pagamento, não inferência.
- **O PagBank é o inverso**: manda estado e nenhum evento, então ali o `type` sai do
  status. É a única inferência do conjunto, e está marcada como tal no PHPDoc.
- **O mapeamento mora no enum de cada gateway** (`WebhookEventsEnum::of()`), como o
  `PaymentStatus` do #133, e o parsing numa classe `*WebhookEventRequest` estática,
  como os outros `*Request`. `EventType::UNKNOWN` com `rawType` preenchido é
  degradação; levantar perderia notificação que o gateway não repete.
- **Corpo vazio ou não-JSON levanta `ValidationException`**, e isso não contradiz o
  acima: é integração que não repassou o payload, não evento que a lib não conhece.
  Mesma distinção do `SignatureRequest`.
- **Rede e AbacatePay não declaram.** Da Rede sei os nomes dos eventos
  (`PV.UPDATE_TRANSACTION_PIX`, `PV.REFUND_PIX`) mas **não os campos do envelope**, e
  chutar a chave da lista devolveria zero eventos em silêncio. Da AbacatePay o payload
  da v1 não está documentado. Parser sobre forma chutada é pior que
  `NotImplementedException`.
- Na Cielo, só os `ChangeType` inequívocos ganham tipo (2 criada, 7 chargeback, 8
  alerta de fraude). O 25 é "cancelada **ou** estornada parcialmente" e o 6 é boleto
  pago a menor, que o vocabulário de evento não cobre — os dois ficam `UPDATED`, em
  vez de escolher e errar metade das vezes.

## Assinatura de webhook

Use **`webhookSignature()`**. Cadastrar o endpoint é `SupportsWebhooks`; provar que
a notificação veio do gateway é `SupportsWebhookSignature`, e são coisas diferentes
— cinco gateways assinam, quatro não.

- O corpo verificado é sempre o **cru**. `PHPay\Webhooks\SignatureRequest` recusa
  corpo vazio com uma mensagem que explica isso, porque reserializar o JSON muda o
  hash e o erro voltaria como assinatura inválida.
- Header é procurado por **`PHPay\Webhooks\Headers::find()`**, que normaliza caixa,
  prefixo `HTTP_` e `_`/`-`. Não compare chave de header na mão: o mesmo header
  chega como `x-signature`, `X-Signature` e `HTTP_X_SIGNATURE`.
- Comparação **sempre** com `hash_equals`.
- `verify()` devolve `bool`, porque assinatura que não bate é resposta. O modelo de
  erro continua valendo para uso errado — segredo ausente, corpo vazio ou header de
  assinatura que não chegou levantam `ValidationException`.
- Gateway novo só declara a capacidade se a documentação dele descrever o esquema.
  **Não existe verificador que devolve `true` por não ter o que checar**: seria pior
  que não ter verificação, porque passaria a impressão de ter.
- A Woovi é a única que faz HTTP para verificar (busca as chaves públicas em
  `api/v1/webhook/public-keys` e aceita qualquer uma da lista, por causa da
  rotação), por isso o recurso dela recebe `?Client $client = null` como os outros.
  Os demais são só criptografia e não recebem client.

## Particularidades por gateway

- **Asaas** — `$sandbox` troca a base URL. Chaves Pix próprias, porque é PSP (como Woovi e Efí).
  Assinatura cobre os 14 endpoints da API. **O carnê responde PDF**, por isso usa
  `HasHttpClient::download()`, que devolve o corpo cru — `request()` decodifica
  JSON e devolveria `[]`. **`destroy()` não é estorno** — a doc do Asaas pede para não tratar a exclusão
  como devolução; quem devolve é `refund()` (`payments/{id}/refund`, reais, cartão e
  Pix), e o boleto tem endpoint assíncrono próprio, fora da capacidade.
  `destroy()` leva as cobranças pendentes e vencidas;
  a pausa é `deactivate()`, e **reativar exige um novo `nextDueDate`**. `cycle` é
  obrigatório na criação. Atualização de assinatura é `PUT`, como o resto do Asaas.
- **Efí** — **duas APIs com as mesmas credenciais**: Cobranças (`cobrancas.api...`,
  trait `HasEfiClient`, boleto em `charge()`) e Pix (`pix.api...`, trait
  `HasEfiPixClient`, **só por mTLS**). Cada API tem o seu token (`getToken()` e
  `getPixToken()`), em cache no gateway e renovado ao expirar, com margem de 30s.
  A cobrança Pix é `pixCharge()`, **extra do gateway concreto**: `charge()` já é o
  boleto e mudar o retorno quebraria a v2. **Na API Pix, valor só como `Money`**
  (reais em string, `toDecimal()`), porque a API de Cobranças do mesmo gateway usa
  centavos — não abra `Money|int` ali. O certificado só é exigido quando o recurso
  monta o próprio client, por isso os testes injetam `pixClient` e não precisam de
  arquivo. Webhook é **um por chave Pix**, endereçado pela chave. Chaves Pix: só EVP.
  Rotas conferidas no SDK oficial (`efipay/sdk-php-apis-efi`), e status e campos do
  Pix Automático na especificação do BACEN (`bacen/pix-api`, `openapi.yaml`).
- **PagBank** — **duas APIs em hosts diferentes**: pedidos em `api.pagseguro.com`,
  assinaturas em `api.assinaturas.pagseguro.com`. O trait expõe `clientPagBankBoot()`
  e `clientPagBankSubscriptionsBoot()`; cada recurso boota o seu. **Todo valor é
  inteiro em centavos** — os validadores recusam decimal, porque mandar `10.50` onde
  se espera `1050` cobra onze centavos. Pix é `qr_codes` do pedido (um só por pedido,
  copia-e-cola em `qr_codes[0].text`), não uma `charge`.
- **Woovi/OpenPix** — **segundo gateway com as cinco capacidades**, junto com o Asaas.
  AppID vai **cru** no `Authorization`, sem esquema. Sandbox tem **domínio próprio**
  (`api.woovi-sandbox.com`). O webhook fica em `api/openpix/v1/` enquanto os demais
  recursos ficam em `api/v1/` — herança da fusão das marcas, não erro. Todo objeto é
  endereçável pelo `correlationID` (id do sistema de quem integra), então `find()` e
  `destroy()` aceitam os dois ids. Valores em centavos. **No estorno encontram-se dois
  `correlationID` diferentes**: o da URL endereça a cobrança, o do corpo é id de
  idempotência do próprio estorno, gerado com `Idempotency::key()` — mandar o da
  cobrança no corpo deduplica contra o objeto errado.
- **AbacatePay** — host único e **sem prefixo de chave**: não dá para derivar o
  ambiente da credencial, então **não existe `isSandbox()`** — inventar convenção aqui
  seria mentira. A resposta da cobrança traz `devMode`, e é isso que `isDevMode()` lê.
  Cobrança é montada por **produtos**, não por valor; preço em centavos com mínimo de
  100. `frequency` só aceita `ONE_TIME`, por isso sem assinaturas. Cupons são extra do
  gateway concreto, como o `webhookDeliveries()` do Pagar.me.
- **Rede** — **host de OAuth separado do host de API**, e o caminho do token muda por
  ambiente (`oauth2/token` vs `redelabs/oauth2/token`) — está em `RedeEnvironment`,
  fora do trait, para o gateway ler sem puxar os verbos HTTP. **O token expira**: é o
  único gateway com ciclo de vida de credencial, tratado em `Resources/Authorization`
  com margem de 30s antes do vencimento. O `Charge` pede um token a cada chamada e
  injeta como Bearer por requisição, em vez de fixar no header do client.
- **Cielo** — **dois hosts separados por tipo de operação**, não por domínio: escritas
  em `api.cieloecommerce...`, consultas em `apiquery.cieloecommerce...`. O **mesmo
  recurso** usa os dois, por isso `HasHttpClient::request()` aceita um client opcional
  e o trait expõe `queryGet()`. Autenticação por headers `MerchantId`/`MerchantKey`.
  Valores em centavos. Recorrência **não tem endpoint de criação**: nasce de uma venda
  com bloco `RecurrentPayment`. Os endpoints de update da recorrência recebem um valor
  JSON puro no corpo (`19900`, `"Monthly"`), não um objeto — daí o `putValue()`.
- **Pagar.me** — autenticação **Basic** (secret key como usuário, senha vazia), não
  Bearer. Ambiente pelo prefixo `sk_test_`, host único, então sem `$sandbox`. Valores
  em centavos. Cancelamento é `DELETE /charges/{id}` com valor opcional no corpo —
  use `request('DELETE', ...)`, porque `delete()` do trait não manda corpo.
  `webhookDeliveries()` é **extra do gateway concreto**, não capacidade: `/hooks` lê
  entregas, não cadastra endpoints.
- **Mercado Pago** — **não tem URL de sandbox**: o ambiente vem do prefixo `TEST-` do
  access token, então o construtor não recebe `$sandbox`. `POST /v1/payments` exige
  `X-Idempotency-Key` (por isso `HasHttpClient::post()` aceita headers por requisição).
  Cliente não é pré-requisito de cobrança, e a API não oferece exclusão de cliente.
- **Inter** — **primeiro banco, e o único com token emitido por escopo**. `scope` é
  obrigatório na requisição do token e não existe token que carregue tudo, então o
  gateway pede a união do que as capacidades usam (`InterGateway::SCOPES`); pedir
  escopo que a aplicação não tem reprova a requisição inteira, por isso `lotecobv.*`
  e `payloadlocation.*` ficam fora da constante. **Gerar token é limitado a 5 chamadas
  por minuto** — cache não é otimização, é o que evita `429`. Host único, com mTLS em
  tudo, inclusive o token; sandbox em **domínio próprio** (`uatinter.co`), como a
  Woovi. Token em `form_params` com as credenciais no corpo, não em Basic como a Efí.
  `charge()` é a cobrança **Pix** — na Efí `charge()` é o boleto —, porque o boleto do
  Inter é outra API (`/cobranca/v3`) que a lib não fala. **Não existe `qrCode()`**: o
  Inter não expõe `/loc/{id}/qrcode`, e o copia-e-cola volta na própria cobrança.
  **Não existe listagem de webhook** — a taxonomia de erros da referência menciona
  `GET /webhook`, mas aquela seção é o texto do BACEN copiado inteiro e nomeia
  endpoints que o gateway não serve; não acredite nela sem conferir a lista de
  operações. O callback vai para `<webhookUrl>/pix`, não para a URL cadastrada.
  Header `x-conta-corrente` só quando a aplicação está em mais de uma conta. Valor só
  como `Money` (reais em string), como na API Pix da Efí.

## O que ainda está em aberto

- Da API Pix da Efí ficaram de fora: Pix Automático pela jornada 1 (`solicrec`,
  notificação no app do pagador), webhooks de recorrência e de cobrança recorrente
  (`webhookrec`, `webhookcobr`), envio de Pix e split.
- `SupportsRefunds` fica em sete dos dez gateways. Os três de fora têm motivo, e não
  são stub: a devolução Pix da **Efí** e do **Inter** exige o valor (o BACEN pede
  `valor`, enquanto a capacidade promete que ausente estorna integral) e é endereçada
  pelo `endToEndId`, não pela cobrança — continua em `pixCharge()->refund()` na Efí e
  em `charge()->refund()` no Inter; e na **AbacatePay** os endpoints de reembolso documentados são da v2, para checkout e
  link de pagamento, enquanto a lib fala com a v1 (`billing/create`). Também ficaram
  fora, de propósito: `capture()` (Cielo, Pagar.me, Rede), que é captura de
  pré-autorização e não devolução; o estorno de boleto do Asaas, que é endpoint
  assíncrono próprio e devolve `requestUrl`; e a listagem de estornos.
- `SupportsWebhookEvents` fica em oito dos dez gateways. **Rede** e **AbacatePay**
  ficam de fora por formato não confirmado, e entram quando alguém verificar em
  sandbox — da Rede falta o nome dos campos do envelope, da AbacatePay o payload da
  v1 (e a doc deles recomenda não validar o schema, que muda). **O Inter é a prova de
  que essa cautela não é teatro**: a API Pix dele é a especificação do BACEN quase
  literal, então o envelope `{"pix": [...]}` era a aposta óbvia — e está errada, o
  corpo dele é o array direto. Chutar devolveria zero eventos em silêncio.
- Chave de idempotência existe em três gateways: Mercado Pago, Pagar.me e PagBank. Nos
  outros sete, `POST` só é repetido quando o erro provou que a requisição não saiu da
  máquina. O `correlationID` da Woovi deduplica de fato, mas vive no corpo e não em
  header, então o middleware não o vê.
- `SupportsWebhookSignature` fica em cinco gateways: Asaas, PagBank, Mercado Pago,
  Woovi e AbacatePay. Efí e Inter (mTLS), Cielo, Rede e Pagar.me ficam fora, cada um
  pelo seu motivo, e os motivos estão em `docs/webhooks.md`. O HMAC-SHA1 da Woovi é aceito, mas é
  depreciado pela própria Woovi.
- `SupportsWebhooks` fica no Asaas, no Woovi, na Efí e no Inter; `SupportsPixKeys` só
  nos três primeiros. O Inter cadastra webhook por chave Pix como a Efí, mas a API
  dele **não tem endpoint de DICT**: a chave que endereça o webhook é uma que você já
  tem, e não há como criar, listar ou remover. Mercado Pago, PagBank e Pagar.me
  registram endpoints por painel, e Pix neles é forma de pagamento. Não
  "resolva" isso criando stubs — e não declare a capacidade por causa de uma API
  parecida: o `/hooks` do Pagar.me lê entregas, é outra coisa, e por isso virou um
  recurso fora do modelo.
- `Efi\Resources\Charge\Charge` tem `$items` e `$configuration` privados sem setter —
  hoje sempre caem no fallback (`getItems()` monta um item a partir de
  `description`/`value`; `getConfigurations()` usa fine 200 / interest 33).
- O CI roda a matriz do Pest em 8.2/8.3/8.4, porque o toolchain de dev exige
  8.2+. O 8.1 tem um job próprio (`minimum`), que não roda a suíte: valida o
  `composer.json`, instala com `--no-dev` depois de liberar o `config.platform`
  (o `update --no-dev` ainda resolveria o require-dev e esbarraria no pint),
  confere a sintaxe de `src/` com `php -l` e roda `.github/scripts/smoke.php`.
  O smoke test instancia todos os gateways e percorre as capacidades sem sair
  para a rede; gateway novo que não entre na lista dele reprova o job. O estorno é
  tratado à parte nesse percurso: por ser operação e não getter, chamá-lo num gateway
  que o declara sairia para a rede — ali só o contrato é conferido, e nos que não
  declaram se exercita a recusa, que acontece antes de qualquer HTTP. A
  cobertura sai de um job só (8.3/locked, pcov), no log e no summary, sem
  mínimo que reprove o build.

## mTLS

Use **`PHPay\Http\Certificate`**. O padrão do BACEN para API Pix exige mTLS em toda
requisição, inclusive a do token, e Inter, BB, Itaú, Sicoob e Sicredi seguem o mesmo
esquema — por isso o certificado é genérico, não da Efí.

- `guzzleOptions()` devolve `['cert' => ...]` e, num par, também `ssl_key`. Some isso à
  config do `Client` que o trait monta; **não** passe `CURLOPT_SSLCERTTYPE` em `curl`,
  porque o Guzzle recente recusa opção cURL que conflita com a dele, e ele já deduz
  `P12` pela extensão.
- **Os PSPs entregam o certificado de duas formas, e as duas são aceitas.** A Efí manda
  um `.p12` com certificado e chave dentro (`new Certificate($path, $senha)`); o Inter
  manda `.crt` e `.key` separados (`Certificate::pair($crt, $key, $senha)`). A doc do
  próprio Inter manda juntar com `openssl pkcs12 -export`, mas o cURL aceita as duas
  metades como estão, e `ssl_key` existe nas duas majors do Guzzle — sem opção de tipo,
  porque PEM é o default do cURL para a chave.
- **Num par a senha vai na chave, nunca no certificado.** O Guzzle transforma o segundo
  elemento de `cert` em `CURLOPT_SSLCERTPASSWD`, e o `.crt` é público: não tem o que
  decifrar. É o erro que falha em silêncio, por isso tem teste próprio.
- Arquivo único: só `.p12` e `.pem`. Um `.pfx` é o mesmo formato, mas o Guzzle não o
  reconhece pela extensão: a mensagem manda renomear. Par: `.crt`, `.cer` ou `.pem` no
  certificado e `.key` ou `.pem` na chave. **Não acrescente `crt` a `FORMATS`** para
  "aceitar o Inter" — um certificado sozinho não tem chave privada e não faz mTLS; a
  mensagem de formato aponta o `pair()`.
- `fromBase64()` e `pairFromBase64()` gravam em temporários 0600, apagados ao fim do
  processo. O par **não** tem parâmetro de formato: ali a extensão não informa nada, já
  que as duas metades são PEM. No arquivo único a extensão é o que diz ao cURL se é um
  bundle PKCS#12 ou um PEM.
- `__debugInfo()` mascara a senha. Não crie getter para ela.

## Segurança

Biblioteca de pagamentos: nunca logar, imprimir ou commitar tokens, `access_token`,
`clientSecret` ou CPF/CNPJ reais. `$sandbox = true` é o default em todos os construtores —
mantenha assim.

- **`PHPay\Http\MasksCredentials`** dá `__debugInfo()` a todo gateway e, pelo
  `HasHttpClient`, a todo Resource. No `var_dump`/`print_r`, ele troca por `********` as
  propriedades listadas em `credentialProperties()` e esconde todo `Client`, que carrega a
  credencial nos headers. **Credencial com nome novo precisa entrar nessa lista.** Gateway
  novo usa o trait.
- O `dd()` do Symfony VarDumper lê propriedade privada por conta própria e **não** é
  coberto — não prometa isso na documentação.
- Parâmetro que recebe credencial leva `#[\SensitiveParameter]`, para não aparecer em stack
  trace. O atributo é ignorado no 8.1 sem erro.
- Exceção nunca carrega resposta com token: antes de passar o corpo para a
  `ApiException`, tire `access_token`/`refresh_token` (ver `EfiGateway::withoutCredentials()`).
