# CLAUDE.md

Orientações para o Claude Code trabalhar neste repositório.

## O que é

PHPay (`phpay-io/phpay`) é uma **biblioteca PHP** (não uma aplicação) que padroniza a
integração com gateways de pagamento brasileiros. Hoje suporta **Asaas** e
**Woovi/OpenPix** (as cinco capacidades), **Efí** (todas menos clientes),
**Mercado Pago**, **PagBank** e **Pagar.me** (clientes, cobranças, assinaturas),
**Cielo** (cobranças e recorrência), **AbacatePay** (clientes e cobranças) e
**Rede** (cobranças).

Requisitos: PHP `^8.1` para consumir a lib; `^8.2` para rodar o ambiente de dev
(Pest 3 e Termwind 2 exigem 8.2+). Dependências de runtime: `ext-curl`, `ext-json`,
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
   │ trait HasAsaasClient / HasEfiClient  →  PHPay\Http\HasHttpClient (get/post/put/patch/delete)
   ▼
Requests (validação estática dos payloads antes de qualquer chamada HTTP)
```

Regras que valem para todo código novo:

- **Capacidade, não contrato único.** `GatewayInterface` carrega só `name()`. Cada
  recurso é uma interface em `src/Contracts/` (`SupportsCustomers`, `SupportsCharges`,
  `SupportsWebhooks`, `SupportsPixKeys`, `SupportsSubscriptions`) que o gateway
  implementa se — e só se — oferecer aquele recurso. **Nunca** declare um recurso para
  depois lançar de dentro dele.
- **Recurso novo = interface nova + case na enum `Capability` + método guardado na
  facade.** O guard é `instanceof` seguido de
  `NotImplementedException::forCapability()`; o PHPStan usa esse `instanceof` para
  estreitar o tipo, então não troque por um helper genérico.
- **A facade `PHPay` não conhece gateway concreto**, por isso ela checa em runtime.
  Quem segura o gateway concreto ganha a checagem em tempo de análise.
- **Cada Resource tem uma Interface própria** em `Resources/<Nome>/Interface/`.
- **Resource é descartável e carrega estado via setters fluentes** (`setCharge`,
  `setCustomer`, `setCustomerId`, `setQueryParams`, `setFilter`), sempre com `return $this`
  tipado pela interface, e um método terminal (`create`, `getAll`, `find`, …).
- **Todo Resource aceita `?Client $client = null` como último parâmetro do construtor**
  e faz `$this->client = $client ?? $this->client<Gateway>Boot()`. É isso que torna a
  suíte testável sem rede — não crie Resource sem esse parâmetro.
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
composer test        # lint + unit + types — mesmo gate do CI
composer test:lint   # pint --test (PSR-12 + regras do pint.json)
composer test:unit   # pest
composer test:types  # phpstan (nível e phpVersion vêm do phpstan.neon)
composer lint        # pint -v (corrige)
```

Ou via Docker (`make help` lista tudo):

```bash
make start && make install && make test
```

Rodar um subconjunto:

```bash
./vendor/bin/pest --filter="reaproveita o cliente"
./vendor/bin/pest --group=asaas   # grupos: phpay, asaas, efi
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
- Todo teste declara um grupo: `->group('phpay' | 'asaas' | 'efi')`.
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
  **Nunca adicione linhas de co-autoria ou atribuição em commits e PRs.**
- **Hooks Husky:** `pre-commit` (pint + phpstan), `pre-push` (pest), `commit-msg`.
  Instalados por `npm install`.
- **Namespaces:** `PHPay\` → `src/`, e um root por gateway: `PHPay\Asaas\`,
  `PHPay\Efi\`, `PHPay\MercadoPago\` → `src/Gateways/<Gateway>/`. Gateway novo
  precisa de um root novo no `composer.json` — não introduza `PHPay\Gateways\...`.

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

## Particularidades por gateway

- **Asaas** — `$sandbox` troca a base URL. Chaves Pix próprias, porque é PSP (como Woovi e Efí).
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
  `destroy()` aceitam os dois ids. Valores em centavos.
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

## O que ainda está em aberto

- `Subscription` só implementa `create()`. Listar, buscar, atualizar, cancelar,
  carnê e NFe seguem pendentes na API do Asaas.
- Da API Pix da Efí ficaram de fora: Pix Automático pela jornada 1 (`solicrec`,
  notificação no app do pagador), webhooks de recorrência e de cobrança recorrente
  (`webhookrec`, `webhookcobr`), envio de Pix e split.
- `SupportsWebhooks` e `SupportsPixKeys` só no Asaas, no Woovi e na Efí — os três são
  PSP. Mercado Pago, PagBank e Pagar.me registram endpoints por painel, e Pix neles é
  forma de pagamento. Não
  "resolva" isso criando stubs — e não declare a capacidade por causa de uma API
  parecida: o `/hooks` do Pagar.me lê entregas, é outra coisa, e por isso virou um
  recurso fora do modelo.
- `Efi\Resources\Charge\Charge` tem `$items` e `$configuration` privados sem setter —
  hoje sempre caem no fallback (`getItems()` monta um item a partir de
  `description`/`value`; `getConfigurations()` usa fine 200 / interest 33).
- O CI roda a matriz em 8.2/8.3/8.4; a compatibilidade com 8.1 é garantida
  estaticamente pelo `phpVersion: min: 80100` do `phpstan.neon`, não por execução real.

## mTLS

Use **`PHPay\Http\Certificate`**. O padrão do BACEN para API Pix exige mTLS em toda
requisição, inclusive a do token, e Inter, BB, Itaú, Sicoob e Sicredi seguem o mesmo
esquema — por isso o certificado é genérico, não da Efí.

- `guzzleOptions()` devolve `['cert' => ...]`. Some isso à config do `Client` que o
  trait monta; **não** passe `CURLOPT_SSLCERTTYPE` em `curl`, porque o Guzzle recente
  recusa opção cURL que conflita com a dele, e ele já deduz `P12` pela extensão.
- Só `.p12` e `.pem`. Um `.pfx` é o mesmo formato, mas o Guzzle não o reconhece pela
  extensão: a mensagem manda renomear.
- `fromBase64()` grava num temporário 0600, apagado ao fim do processo.
- `__debugInfo()` mascara a senha. Não crie getter para ela.

## Segurança

Biblioteca de pagamentos: nunca logar, imprimir ou commitar tokens, `access_token`,
`clientSecret` ou CPF/CNPJ reais. `$sandbox = true` é o default em todos os construtores —
mantenha assim.
