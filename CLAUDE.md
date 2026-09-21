# CLAUDE.md

Orientações para o Claude Code trabalhar neste repositório.

## O que é

PHPay (`phpay-io/phpay`) é uma **biblioteca PHP** (não uma aplicação) que padroniza a
integração com gateways de pagamento brasileiros. Hoje suporta **Asaas** (as cinco
capacidades), **Mercado Pago** e **PagBank** (clientes, cobranças, assinaturas) e
**Efí** (cobranças).

Requisitos: PHP `^8.1` para consumir a lib; `^8.2` para rodar o ambiente de dev
(Pest 3 e Termwind 2 exigem 8.2+). Dependências de runtime: `ext-curl`, `ext-json`,
`guzzlehttp/guzzle ^7`. Publicado no Packagist.

## Arquitetura

```
PHPay (facade)  ──implements──▶  PHPay\Contracts\GatewayInterface
   │ delega tudo para o gateway injetado no construtor
   ▼
AsaasGateway / EfiGateway  ──implements──▶  <Gateway>Interface extends GatewayInterface
   │ cada método (customer/charge/pix/webhook/subscription) devolve um Resource novo
   ▼
Resources (Customer, Charge, Pix, Webhook, Subscription)
   │ trait HasAsaasClient / HasEfiClient  →  PHPay\Http\HasHttpClient (get/post/put/delete)
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

## Particularidades por gateway

- **Asaas** — `$sandbox` troca a base URL. Único com chaves Pix, porque é PSP.
- **Efí** — autoriza sob demanda (token em cache no gateway); `$sandbox` troca a base URL.
- **PagBank** — **duas APIs em hosts diferentes**: pedidos em `api.pagseguro.com`,
  assinaturas em `api.assinaturas.pagseguro.com`. O trait expõe `clientPagBankBoot()`
  e `clientPagBankSubscriptionsBoot()`; cada recurso boota o seu. **Todo valor é
  inteiro em centavos** — os validadores recusam decimal, porque mandar `10.50` onde
  se espera `1050` cobra onze centavos. Pix é `qr_codes` do pedido (um só por pedido,
  copia-e-cola em `qr_codes[0].text`), não uma `charge`.
- **Mercado Pago** — **não tem URL de sandbox**: o ambiente vem do prefixo `TEST-` do
  access token, então o construtor não recebe `$sandbox`. `POST /v1/payments` exige
  `X-Idempotency-Key` (por isso `HasHttpClient::post()` aceita headers por requisição).
  Cliente não é pré-requisito de cobrança, e a API não oferece exclusão de cliente.

## O que ainda está em aberto

- `Subscription` só implementa `create()`. Listar, buscar, atualizar, cancelar,
  carnê e NFe seguem pendentes na API do Asaas.
- A Efí só tem autorização e cobranças; `customer`, `webhook`, `pix` e `subscription`
  lançam `NotImplementedException`.
- Nem o Mercado Pago nem o PagBank implementam `SupportsWebhooks` ou `SupportsPixKeys`,
  e isso é correto: webhooks só têm configuração por painel ou `notification_url(s)` por
  cobrança, e Pix nos dois é forma de pagamento. Não "resolva" isso criando stubs.
- `Efi\Resources\Charge\Charge` tem `$items` e `$configuration` privados sem setter —
  hoje sempre caem no fallback (`getItems()` monta um item a partir de
  `description`/`value`; `getConfigurations()` usa fine 200 / interest 33).
- O CI roda a matriz em 8.2/8.3/8.4; a compatibilidade com 8.1 é garantida
  estaticamente pelo `phpVersion: min: 80100` do `phpstan.neon`, não por execução real.

## Segurança

Biblioteca de pagamentos: nunca logar, imprimir ou commitar tokens, `access_token`,
`clientSecret` ou CPF/CNPJ reais. `$sandbox = true` é o default em todos os construtores —
mantenha assim.
