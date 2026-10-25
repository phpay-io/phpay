# Changelog

Todas as mudanças relevantes do PHPay são registradas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/), e o
projeto adota [Versionamento Semântico](https://semver.org/lang/pt-BR/).

O de-para completo da v1 para a v2, quebra por quebra, está no
[UPGRADE.md](UPGRADE.md).

## [Não lançado]

## [2.8.0] - 2026-10-24

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.8.0) ·
Décimo gateway, e o primeiro de um banco: o **Inter**. Para ele, o
`Certificate` passou a aceitar certificado e chave em **arquivos separados**,
que é como os bancos entregam. Só entraram métodos novos; nenhuma assinatura
existente mudou.

### Adicionado

- **Gateway do Inter**, o décimo e o primeiro de um banco: cobranças Pix
  (imediata e com vencimento), webhooks por chave Pix e leitura de notificação
  ([#156]).
- O Inter é o **primeiro gateway cujo token é emitido por escopo**. `scope` é
  obrigatório na requisição e não existe token que carregue todas as permissões,
  então o gateway pede a união do que as capacidades dele usam
  (`InterGateway::SCOPES`); aplicação com permissão mais estreita passa a sua em
  `scopes:` ([#156]).
- O cache de token do Inter **não é otimização**: gerar um é limitado a 5
  chamadas por minuto, e a própria documentação manda reaproveitar por toda a
  hora de validade. Um processo que pedisse token por requisição levaria `429`
  na sexta ([#156]).
- `account:` preenche o header `x-conta-corrente`, que o Inter exige só quando a
  aplicação está vinculada a mais de uma conta corrente. Sem valor, o header não
  é mandado ([#156]).
- Documentação: [Inter](docs/gateways/inter.md), e a tabela de capacidades do
  README ganhou as colunas de **estornos** e **leitura de webhook**, que faltavam
  desde que as duas capacidades entraram ([#156]).

### Notas sobre a API do Inter

A API Pix dele é a especificação do BACEN quase literal — mesmos caminhos,
mesmos schemas, mesma taxonomia de erro. **A notificação é onde ela diverge, e
as duas divergências falham em silêncio:**

- **o corpo é um array na raiz**, não o `{"pix": [...]}` do padrão e da Efí. Um
  parser escrito pela especificação não acharia a chave `pix` e devolveria zero
  eventos para todo pagamento de verdade. O envelope `pix` também é aceito,
  porque a "Evolução da API Pix" da referência reserva o direito de acrescentar
  campos a qualquer momento;
- **`devolucoes` é um objeto**, onde o padrão manda array. Pode ser erro de
  documentação deles, então as duas formas são lidas.

Três coisas que a referência tem e o gateway **não** expõe, conferidas na lista
de operações: gestão de chave Pix (não há endpoint de DICT), `/loc/{id}/qrcode`
— o copia-e-cola volta na cobrança — e a listagem de webhooks. A taxonomia de
erros menciona um `GET /webhook`, mas aquela seção é o texto do BACEN copiado
inteiro e nomeia endpoints que o Inter não serve.

O callback chega na URL cadastrada. A expressão `{$request.body#/webhookUrl/pix}`
que o Redoc mostra na referência **não** quer dizer que o Inter acrescente
`/pix`: o `#/` introduz um JSON Pointer, e o que houve é que o callback se chama
`pix` na especificação e o renderizador juntou o nome à expressão. O sufixo
`/pix` é quirk do Itaú, não do Inter.

### Segurança

- O `.gitignore` passou a ignorar `*.crt`, `*.cer` e `*.key`, além dos formatos
  que já cobria. O Inter entrega a chave privada em arquivo próprio, e uma chave
  commitada não se desfaz com um revert ([#156]).

- **`Certificate::pair()`**, para o PSP que entrega certificado e chave em
  arquivos separados. A Efí manda um `.p12` com as duas coisas dentro, e era o
  único formato aceito; o Inter manda `.crt` e `.key`, e a documentação dele
  manda juntar os dois com `openssl pkcs12 -export` antes de usar. O cURL
  aceita as metades como vêm, então a conversão era atrito que a biblioteca
  podia absorver ([#156]).
- **`Certificate::pairFromBase64()`**, o mesmo para container e serverless,
  onde cada metade chega numa variável de ambiente. Grava os dois temporários
  com permissão `0600`, apagados ao fim do processo ([#156]).
- `Certificate::keyPath()` e `Certificate::isPair()`, e `guzzleOptions()`
  passou a emitir `ssl_key` quando há par. Num par a senha vai na **chave**, e
  não no certificado: o Guzzle transforma o segundo elemento de `cert` em
  `CURLOPT_SSLCERTPASSWD`, e o `.crt` é público — não tem o que decifrar
  ([#156]).
- Documentação: [Certificado e mTLS](docs/mtls.md), que reúne os dois formatos,
  as extensões aceitas e o que o `Certificate` confere ([#156]).

### Corrigido

- A mensagem de formato recusado passou a apontar o `pair()`. Um `.crt` sozinho
  dizia só "use .p12 ou .pem", sem dizer o que fazer com a chave que veio ao
  lado ([#156]).

## [2.7.0] - 2026-10-24

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.7.0) ·
Quem **usa** a biblioteca passa a conseguir testar o próprio código sem sair
para a rede. Só entraram métodos novos; nenhuma assinatura existente mudou.

### Adicionado

- **`ClientFactory::fake()`**, para quem **usa** a biblioteca testar o próprio
  código sem sair para a rede. A suíte daqui injeta `Client` nos Resources, mas
  quem consome recebe o gateway montado — sobretudo resolvendo por nome — e o
  registry não aceita `Client`. Sem isso, uma aplicação não escrevia teste de
  controller, de listener de webhook nem de job sem falar com o gateway de
  verdade ([#152]).
- O modo falso alcança **todos** os clients de uma vez, inclusive os segundos
  que Efí, Cielo e Rede mantêm para outro host. Aceitar `Client` no registry
  falsearia metade — e na Rede, que pede token antes de cada chamada num host
  separado, a outra metade iria para a rede na primeira linha do teste. Como
  toda a biblioteca monta client em `ClientFactory::make()`, falsear ali resolve
  uniformemente ([#152]).
- `ClientFactory::recorded()` devolve as requisições que a aplicação fez, com
  `request` e `options`. O retry continua instalado em modo falso, e o gravador
  fica por fora dele — o histórico mostra as chamadas, não cada tentativa
  ([#152]).

## [2.6.0] - 2026-10-24

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.6.0) ·
Os cinco verificadores de assinatura ganharam contrato comum, para que código
que não sabe o gateway em tempo de escrita tenha o que tipar. Nenhuma quebra: a
capacidade continua devolvendo `object` de propósito, e quem explica por quê é a
seção **Adicionado**.

### Adicionado

- **`PHPay\Webhooks\VerifiesSignature`**, o contrato comum dos cinco verificadores
  de assinatura, e **`PHPay::webhookSignature()` passa a devolvê-lo** em vez de
  `object`. Quem segura o gateway concreto já tinha o tipo; quem **não** pode
  segurá-lo — bridge de framework, middleware genérico, qualquer código que resolve
  o gateway por nome — recebia `object` e não tinha o que tipar. Os setters de
  esquema específico (`setDataId`, `setQuery`, `setSignatureKey`, `setPublicKeys`)
  ficam fora do contrato de propósito ([#146]).
- **A capacidade `SupportsWebhookSignature` continua devolvendo `object`**, de
  propósito: estreitá-la seria compatível para quem consome e **fatal para quem
  implementa a capacidade num gateway próprio**, porque tipo de retorno é
  covariante. Como ela não exige o contrato, a facade confere em runtime e recusa
  com `ValidationException` um verificador que não o cumpra. Quem tipa a capacidade
  para injeção de dependência ainda recebe `object`; fechar isso exige major
  ([#146]).

## [2.5.0] - 2026-10-23

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.5.0) ·
Estorno, status e leitura de webhook ganharam forma única entre os gateways, o
Guzzle 8 passou a ser suportado de verdade — comportamento e tipos — e o gateway
passou a poder vir de configuração. Nenhuma assinatura existente mudou de
comportamento; duas quebram em caso de borda, e estão em **Alterado** e
**Removido**.

### Adicionado

- **Estorno virou capacidade**, em `PHPay\Contracts\SupportsRefunds`, com o case
  `REFUNDS` na enum `Capability` e `refund()` guardado na facade. Seis gateways
  já estornavam, cada um com um nome e uma assinatura diferente, e **nenhum era
  alcançável pela facade** — quem usava `PHPay` tinha que descer para o gateway
  concreto e saber de antemão se o método se chamava `refund` ou `cancel`. Agora
  é uma chamada só, `$phpay->refund($id)` ou `$phpay->refund($id, $valor)`, com
  `null` estornando integral nos sete que a declaram ([#132]).
- **Asaas e Woovi passam a estornar** — os dois gateways completos não tinham o
  método, embora a API dos dois exponha o endpoint. No Asaas é
  `POST payments/{id}/refund`, em reais, para cartão e Pix, e explicitamente
  **não** é o `destroy()`, que remove cobrança não paga. Na Woovi é
  `POST api/v1/charge/{correlationID}/refund`, em centavos, com um segundo
  `correlationID` no corpo que é id de idempotência do estorno — não o da
  cobrança ([#132]).
- `refund()` na **Cielo** e no **Pagar.me**, como o nome portável sobre o
  endpoint que cada uma já tinha. `cancel()` continua existindo nas duas: ali um
  endpoint só anula autorização e estorna venda paga, e `cancel` é o nome honesto
  da primeira metade ([#132]).
- **`PHPay\Support\PaymentStatus`**, que lê o status da cobrança numa forma só.
  A mesma cobrança paga voltava como `RECEIVED`, `COMPLETED`, `approved`, `PAID`,
  `paid`, `CONCLUIDA` ou `'00'` dependendo do gateway, e quem trocava de gateway
  reescrevia todo `if`. Agora cada enum de status tem `status()`, que traduz um
  case, e `of()`, que lê o valor cru tolerando o que a biblioteca não conhece.
  **Os enums concretos continuam existindo e acessíveis** — quem precisa do
  estado específico daquele gateway não perde nada. Suporte, não capacidade:
  não entra na enum `Capability`, porque não existe gateway que deixe de
  reportar status ([#133]).
- **Resolução de gateway por nome**, em `PHPay\Support\Gateway` e
  `PHPay::make()`. Antes só era possível começar sabendo a classe do gateway e a
  assinatura do construtor dele, o que impedia ler o gateway de configuração
  (`PHPAY_GATEWAY=asaas`) e é pré-requisito de qualquer bridge de framework. Os
  construtores não concordam — seis gateways pedem uma credencial e três pedem
  duas, e **Mercado Pago, Pagar.me e AbacatePay não aceitam `sandbox`** porque o
  ambiente deles vem do prefixo da credencial —, e o registry respeita isso em
  vez de inventar uma forma única. **`PHPay::gateway()` não muda**: continua
  sendo o caminho que dá checagem estática ([#143]).
- Config inválida falha na construção, com mensagem nomeando a chave: credencial
  que falta, nome de gateway que não existe (listando os nove), `sandbox` em
  gateway que não o aceita e **chave desconhecida**. Nenhuma é ignorada em
  silêncio — um `skandbox` engolido é typo que só aparece em produção ([#143]).
- **Leitura de webhook virou capacidade**, em `PHPay\Contracts\SupportsWebhookEvents`,
  com `webhookEvents()` guardado na facade. O #101 provou que a notificação **veio**
  do gateway; esta lê o que ela **diz**. Sete gateways declaram: Asaas, Woovi,
  Pagar.me, PagBank, Efí, Mercado Pago e Cielo. `PHPay\Webhooks\Event` carrega tipo
  normalizado (`EventType`), id do recurso, status (do #133), quando aconteceu e o
  payload cru intacto ([#140]).
- **A leitura devolve `array<Event>`, nunca um evento só.** A API Pix da Efí manda
  `pix` como array — vários pagamentos numa requisição — e a de devolução manda o
  array `devolucoes` inteiro; o PagBank manda o pedido com todas as `charges`.
  Tratar como objeto único perderia pagamento em silêncio ([#140]).
- **`Event::requiresLookup()`**, porque quatro notificações não carregam estado:
  Mercado Pago manda só `data.id`, a Cielo manda três campos, e a API de Cobranças
  da Efí manda um token para trocar. Nesses, `status` é `null` em vez de
  `PaymentStatus::UNKNOWN` — que leria como estado que veio no corpo ([#140]).
- Enums de status onde não havia: **Mercado Pago** (`PaymentStatusEnum`),
  **PagBank** e **Pagar.me** (`ChargeStatusEnum`) e **Efí**, que ganhou dois —
  `ChargeStatusEnum` para a API de Cobranças e `PixChargeStatusEnum` para a API
  Pix, porque são vocabulários diferentes com as mesmas credenciais. Os cinco
  que já existiam (Asaas, Woovi, Cielo, Rede, AbacatePay) ganharam o mapeamento
  ([#133]).
- **Suporte ao Guzzle 8**, ao lado do 7: o `composer.json` passa a aceitar
  `^7.3 || ^8.0`. A `RetryPolicy` continua distinguindo "não saiu da máquina" de
  "deu timeout depois de enviar" nas duas majors — o 8 removeu o
  `getHandlerContext()` que o 7 usa e trocou o `errno` do cURL por classes de
  exceção, então a política pergunta na língua de cada uma, com o
  `NetworkExceptionInterface` do PSR-18 como gatilho comum. A suíte passa nas duas e
  o CI ganhou um job na major de cima ([#121]).

### Alterado

- **Mercado Pago: `Charge::refund()` saiu de `?float $value` para
  `Money|int|float|null $amount`**, normalizado com `Money::asReais()` como o
  resto da biblioteca. Número cru continua lido em reais, então a mudança é
  compatível — **com uma exceção: o parâmetro foi renomeado** de `$value` para
  `$amount`, o que quebra quem o chamava por argumento nomeado
  (`refund($id, value: 10.5)`) ([#132]).


- As anotações dos pontos de contato com o Guzzle passam a descrever **as duas
  majors**, e o **PHPStan nível 9 roda também na 8** — o job do Guzzle 8 no CI
  deixa de rodar só a suíte. A 8 declara o que a 7 deixava solto: forma de array
  fechada em `Client::__construct` e `Client::request()`, `HandlerStack` genérica
  e a assinatura exata do decider em `Middleware::retry`. Em vez de afrouxar o
  nível ou acumular `ignoreErrors`, o que se passa ao Guzzle virou forma de array
  com as opções que a biblioteca de fato usa, a pilha de handlers é montada como
  variável local dentro do `ClientFactory::make()` (declarar `HandlerStack` como
  tipo falha numa das duas majors, com ou sem argumento genérico), e o decider
  aceita `mixed` no quarto argumento, estreitando para `Throwable|null` na
  entrada. **Nenhuma assinatura pública mudou de comportamento** ([#131]).


### Removido

- Três métodos de `PHPay\Rede\Traits\HasRedeClient` que **nenhum caminho da
  biblioteca alcançava**: `clientRedeOauthBoot()`, `oauthUri()` e
  `oauthTokenPath()`. O client de autorização da Rede sempre foi montado em
  `RedeGateway::authorization()`, inline, e o caminho do token sempre foi lido
  de `RedeEnvironment::tokenPath()` — os três eram duplicação morta, e o trait
  é usado pelo recurso de transações, que gasta o token e nunca pede um. São
  métodos `protected` de um trait interno, não superfície pública: nada muda
  para quem consome a biblioteca ([#134]).

## [2.4.0] - 2026-10-22

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.4.0) ·
QR Code estático composto localmente, CI no PHP 8.1 de verdade e a documentação
em `docs/`. Nenhuma assinatura de método mudou; o que mudou de lugar foi a
documentação.

### Adicionado

- O CI passa a rodar no PHP 8.1, a versão mínima que o `composer.json` promete.
  A suíte do Pest continua fora dessa versão — o toolchain de dev exige 8.2+ —
  mas o job valida o `composer.json`, instala só as dependências de runtime,
  confere a sintaxe de `src/` e roda um smoke test que carrega o autoload e
  instancia os nove gateways. Nada muda para quem usa a biblioteca: o requisito
  segue `^8.1`, agora verificado por execução e não só pelo PHPStan
  ([#111], [#112]).
- Cobertura de testes medida no CI, num job só, com o número no log e no resumo
  do job. Sem mínimo que reprove o build ([#111], [#112]).
- `PHPay\Support\StaticPixCode`, que compõe o QR Code estático de Pix (o BR
  Code) a partir da chave, localmente e sem rede — como o padrão do BACEN manda.
  Fecha a assimetria dos gateways que não expõem endpoint para isso, a Efí entre
  eles, sem stub e sem sugerir que a API deles oferece o que não oferece. A suíte
  exige o vetor de teste do manual do BACEN byte a byte, porque CRC errado gera um
  código que não lê ([#109]).
- Verificador de links da documentação no gate, em `composer test:links`. Ele
  confere que todo link relativo aponta para arquivo que existe e que toda
  âncora corresponde a um título de verdade — título dentro de bloco de código
  não conta, que foi como o erro abaixo passou tanto tempo escondido
  ([#124]).

### Alterado

- **A documentação saiu do README para `docs/`**, uma página por conceito e uma
  por gateway, com índice. O README fica com o essencial: o que é, a matriz de
  capacidades, requisitos, instalação e início rápido — de 46 KB para 11 KB.
  **Link externo que apontava para âncora do README deixa de funcionar**; o
  índice de [`docs/`](docs/) cobre todos os destinos ([#113], [#114]).
- A matriz de capacidades ganhou a coluna de **assinatura de webhook**, que
  entrou na v2.3.0 e tinha ficado fora da tabela — quem lia era informado de
  que a biblioteca fazia menos do que faz ([#113], [#114]).

### Corrigido

- **O README renderizava errado da Rede até a Efí.** O bloco de código do
  exemplo de recorrência da Cielo nunca era fechado, e cercas alternam: o
  título `### Rede` e a prosa embaixo dele saíam como código, os exemplos
  seguintes saíam como prosa. Por causa disso, **dez links do sumário**
  apontavam para âncoras que não existiam ([#113], [#114]).
- O link para o README no `.github/SUPPORT.md` apontava para `.github/README.md`
  ([#113], [#114]).

## [2.3.0] - 2026-10-22

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.3.0) ·
Verificação de assinatura de webhook, prazo em toda requisição e credencial fora
de dump. **Nenhuma das mudanças altera assinatura de método**: quem não chama
`webhookSignature()` e já mandava valor em centavos inteiros não vê diferença.

Dois pontos para quem vai atualizar:

- **`ext-openssl` passou a ser requisito de runtime**, por causa da verificação
  RSA da Woovi. Habilite a extensão antes de atualizar.
- **Valor decimal na Efí, no PagBank e no Pagar.me agora lança
  `ValidationException`** em vez de emitir uma cobrança com o valor errado.

### Adicionado

- Capacidade `webhookSignature()` — o lado que *recebe* o webhook. Cadastrar o
  endpoint é `SupportsWebhooks`; provar que a notificação veio do gateway é a
  nova `SupportsWebhookSignature`, com o case `Capability::WEBHOOK_SIGNATURE`
  ([#101], [#102]).
- Verificação de assinatura em cinco gateways, cada um no esquema que a própria
  documentação descreve ([#102]):

  | Gateway | Esquema | O que prova |
  | --- | --- | --- |
  | Asaas | token no header `asaas-access-token` | que o remetente conhece o token — o corpo não é assinado |
  | PagBank | `x-authenticity-token`: SHA-256 de `{token}-{corpo cru}` | que o corpo chegou inteiro |
  | Mercado Pago | `x-signature`: HMAC-SHA256 de `id:…;request-id:…;ts:…;` | que é da sua aplicação e aponta para aquele recurso |
  | Woovi | `x-webhook-signature`: RSA-SHA256 do corpo, contra as chaves publicadas | que o corpo veio da Woovi |
  | AbacatePay | `webhookSecret` na query, mais o HMAC do corpo quando a chave é dada | que a URL é a sua e, com a chave, que o corpo não mudou |

- `PHPay\Webhooks\Headers::find()`, que normaliza caixa, prefixo `HTTP_` e
  `_`/`-` ao procurar o header de assinatura — o mesmo header chega como
  `x-signature`, `X-Signature` e `HTTP_X_SIGNATURE` ([#102]).
- `PHPay\Webhooks\SignatureRequest`, que recusa segredo ausente, corpo vazio e
  header de assinatura que não chegou com `ValidationException`. O corpo
  verificado é sempre o cru: reserializar o JSON muda o hash ([#102]).
- `setPublicKeys()` na Woovi, para verificar sem buscar as chaves por HTTP. A
  Woovi é a única que consulta a API para verificar, e aceita qualquer chave da
  lista publicada, por causa da rotação ([#102]).
- `PHPay\Http\ClientFactory` como único lugar onde um client HTTP nasce, com
  `timeout` de 60s e `connect_timeout` de 10s — o padrão do Guzzle é esperar
  para sempre, e um gateway travado prendia o worker até o processo morrer. Um
  teste varre `src/` e reprova `new Client(` fora da fábrica ([#99], [#100]).
- `PHPay\Http\MasksCredentials`, que dá `__debugInfo()` a todo gateway e, pelo
  `HasHttpClient`, a todo recurso: a credencial vira `********` e o `Client`
  inteiro fica oculto, porque ele carrega o token nos headers. O `dd()` do
  Symfony VarDumper lê propriedade privada por conta própria e **não** é
  coberto ([#100]).
- `#[\SensitiveParameter]` em todo parâmetro que recebe credencial, para não
  aparecer em stack trace ([#100]).

### Alterado

- **`ext-openssl` entra nos requisitos de runtime**, ao lado de `ext-curl` e
  `ext-json`, por causa da verificação RSA da Woovi ([#102]).
- **Valor decimal passa a ser recusado na validação da Efí, do PagBank e do
  Pagar.me**, com mensagem que ensina a conversão e aceitando `Money`. Antes
  `10.50` onde se espera `1050` seguia para o gateway e cobrava onze centavos,
  sem erro nenhum ([#100]).
- Os gateways que declaravam as cinco capacidades passam a declarar seis.
  Código que afirma a lista exata de capacidades precisa ser ajustado ([#102]).
- O HMAC-SHA1 da Woovi (`x-openpix-signature`) é aceito como fallback, mas é
  depreciado pela própria Woovi ([#102]).

### Corrigido

- `Money` recusa `NAN`, `INF` e valor que estouraria os centavos num inteiro de
  64 bits. `NAN` e `INF` convertiam para `0`, e acima de `PHP_INT_MAX` o cast
  dava a volta e virava valor negativo — os dois passavam por todas as
  checagens existentes ([#100]).
- A `ApiException` da Efí deixa de carregar `access_token` e `refresh_token` no
  corpo da resposta ([#100]).

## [2.2.0] - 2026-09-21

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.2.0) ·
Efí Pix com mTLS e assinaturas completas do Asaas. Tudo aditivo para quem
chama a biblioteca.

### Adicionado

- **Efí: API Pix por mTLS**, que leva o gateway de 1 para 4 das 5 capacidades.
  As duas APIs da Efí usam as mesmas credenciais, cada uma com o seu token
  ([#95], [#96]):
  - `pixCharge()` — cobrança Pix imediata ou com vencimento, QR Code e
    devolução. É um extra do gateway concreto, porque `charge()` já é o boleto.
  - `pix()` — chaves Pix aleatórias (EVP).
  - `webhook()` — um webhook por chave Pix, endereçado pela chave, com
    `skipMtlsChecking()` para servidores que não validam o certificado da Efí.
  - `subscription()` — Pix Automático: o pagador autoriza uma vez no app do
    banco e cada ciclo é debitado sem nova aprovação. Cobre a recorrência, o QR
    Code de autorização e a cobrança de cada ciclo.
- `PHPay\Http\Certificate`, mTLS genérico e não exclusivo da Efí — o padrão do
  BACEN para API Pix exige mTLS, e Inter, BB, Itaú, Sicoob e Sicredi seguem o
  mesmo esquema. `Certificate::fromBase64()` grava o certificado num temporário
  `0600`, apagado ao fim do processo, para container e serverless. A senha nunca
  aparece num `var_dump()`. Aceita `.p12` e `.pem` ([#96]).
- Parâmetros opcionais `certificate` e `pixClient` no fim do construtor de
  `EfiGateway` ([#96]).
- **Asaas: assinaturas completas**, dos 14 endpoints da API — antes só existia
  `create()` ([#97], [#98]):
  - `getAll()`, `find()`, `update()`, `destroy()`, `deactivate()` e
    `reactivate()`. `destroy()` leva as cobranças pendentes e vencidas; a pausa
    é `deactivate()`, e reativar exige um novo `nextDueDate`.
  - `getPayments()` — as cobranças já geradas pela assinatura.
  - `paymentBook()` — o carnê, como os bytes do PDF.
  - `updateCreditCard()` — troca o cartão sem cobrar.
  - Nota fiscal emitida a cada cobrança: `create/get/update/destroyInvoiceSettings()`
    e `getInvoices()`.

### Alterado

- **Guzzle mínimo sobe de `^7.0` para `^7.3`**, a primeira versão que entrega
  `.p12` ao cURL pela extensão — e o mTLS depende disso. A 7.3 é de março de
  2021 ([#96]).
- **Na API Pix da Efí o valor entra só como `Money`.** Ela quer reais em string
  (`"123.45"`), enquanto a API de Cobranças do mesmo gateway quer centavos;
  aceitar número cru ali recriaria o erro que o `Money` existe para evitar
  ([#96]).
- **`cycle` passa a ser obrigatório na criação de assinatura do Asaas**, como na
  especificação. O payload sem ele agora lança `ValidationException` antes da
  chamada, em vez de `ApiException` do gateway — a requisição já falhava, só
  falha mais cedo e com mensagem melhor ([#98]).
- `SubscriptionInterface` do Asaas: `create(array $subscription = [])` ganhou
  default e a interface ganhou os métodos novos. Afeta só quem implementa a
  interface fora da biblioteca ([#98]).

### Corrigido

- **Efí: o token da API de Cobranças ficava em cache para sempre.** Um gateway
  vivo num worker de fila passava a tomar 401 quando a Efí expirava o token.
  Agora é renovado sozinho, com margem de 30s, e o da API Pix também ([#96]).

## [2.1.0] - 2026-09-21

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.1.0) ·
`Money`, `Customer` e Woovi/OpenPix. A v2.0.0 normalizou os *nomes dos
métodos* entre gateways, mas deixou os *payloads* crus; esta versão fecha isso.

### Adicionado

- **`PHPay\Support\Money`**, que elimina no tipo o erro de centavos vs reais.
  Dois gateways usam reais decimais e os outros sete usam centavos inteiros, e
  mandar `100.50` num gateway de centavos não quebra a integração — **cobra
  R$ 1,00**, e só aparece na conciliação ([#91], [#92]):
  - `Money::reais()` (aceita float, int e string em notação brasileira, como
    `'1.234,56'`) e `Money::centavos()`.
  - `multiply()`, `plus()`, `equals()` e `format()` → `'R$ 100,50'`.
  - **Recusa mais de duas casas decimais** em vez de arredondar calado:
    `Money::reais(10 / 3)` lança exceção. É o que impede erro de um centavo na
    conciliação.
- `setAmount()` no Asaas, no Mercado Pago e na Efí, onde o valor vivia dentro do
  array de payload ([#92]).
- **`PHPay\Support\Customer`**, uma forma só para o cliente — o mesmo campo
  tinha seis grafias entre os gateways (`cpfCnpj`, `tax_id`, `document`,
  `taxId`, `taxID`, `cpf_cnpj`). Cobre todos os campos obrigatórios de cliente
  dos gateways que têm cliente ([#93], [#94]):
  - `Customer::make()` limpa pontuação de documento e telefone.
  - Carrega o que cada gateway deriva: `isIndividual()`, `documentType()`,
    `firstName()`/`lastName()` e `phoneParts()`.
  - `withExtra()` para campo opcional específico de um gateway, como endereço.
- **Gateway Woovi/OpenPix** — o segundo com as cinco capacidades, ao lado do
  Asaas. AppID vai cru no `Authorization`, o sandbox tem domínio próprio, e todo
  objeto é endereçável pelo `correlationID` do sistema de quem integra, então
  `find()` e `destroy()` aceitam os dois ids ([#89], [#90]).

### Alterado

- Todo método que recebe valor aceita `Money|int` (ou `Money|int|float` nos
  gateways de reais), e todo `setCustomer()` aceita `Customer|array`. **Número
  cru e array continuam funcionando**, lidos na unidade que aquele gateway
  sempre esperou ([#92], [#94]).
- **Quebra para quem implementou `SupportsCustomers` num gateway próprio**:
  `customer(array $customer = [])` virou
  `customer(Customer|array $customer = [])`. Uma implementação com
  `customer(array ...)` passa a dar erro fatal no autoload, porque não pode
  aceitar menos que a interface — ajuste a assinatura. Pela regra estrita do
  SemVer isso seria major; saiu como minor porque o contrato havia sido
  publicado na v2.0.0 horas antes e a falha é imediata, não silenciosa ([#94]).

## [2.0.0] - 2026-09-21

[Release](https://github.com/phpay-io/phpay/releases/tag/v2.0.0) ·
**Primeira versão com quebras de compatibilidade.** De 2 para 8 gateways,
falhas passam a ser exceção, e o projeto ganha CI, guia de migração e licença
nova. O de-para completo está no [UPGRADE.md](UPGRADE.md).

### Adicionado

- **Seis gateways novos**: Mercado Pago, PagBank, Pagar.me, AbacatePay, Cielo e
  Rede ([#74], [#75], [#76], [#77], [#78], [#79], [#81], [#83], [#84], [#85],
  [#86], [#87], [#88]).
- **Interfaces de capacidade**: cada recurso é uma interface que o gateway
  implementa se — e só se — oferecer (`SupportsCustomers`, `SupportsCharges`,
  `SupportsWebhooks`, `SupportsPixKeys`, `SupportsSubscriptions`), mais a enum
  `Capability` para descoberta em runtime e `supports()`, `capabilities()` e
  `name()` na facade. Quem segura o gateway concreto ganha a checagem em tempo
  de análise: `(new RedeGateway(...))->pix()` virou erro de PHPStan, não de
  produção ([#72], [#73]).
- **Exceções tipadas**, todas implementando `PHPayException`:
  `ValidationException` (payload inválido, antes de qualquer HTTP),
  `ApiException` (gateway recusou ou está inacessível) e
  `NotImplementedException` (recurso não suportado, com a mensagem nomeando o
  que aquele gateway oferece). `ApiException` carrega `getStatusCode()`,
  `getResponse()`, `getGateway()` e `isConnectionError()` ([#70], [#71]).
- Recusa de valor decimal na validação dos gateways que usam centavos, antes da
  chamada ([#71]).
- **CI no GitHub Actions**, matriz 8.2/8.3/8.4 com dependências `locked`,
  `lowest` e `highest`; PHPStan nível 9 como fonte única, com `phpVersion`
  mínimo 8.1 garantindo a compatibilidade declarada; e checagem de conformidade
  contra sandbox para Mercado Pago e PagBank, que recusa credenciais de
  produção ([#71], [#77]).
- `UPGRADE.md` com o de-para completo, `CLA.md` e template de pull request
  ([#71], [#81], [#82]).
- Documentação da unidade monetária de cada gateway, que não estava em lugar
  nenhum — e errar não quebra a integração, cobra o valor errado ([#71]).

### Alterado

Os sete itens abaixo **quebram compatibilidade**.

1. **Falhas viram exceção, não array de erro.** Um array retornado é sempre
   sucesso. `destroy()` continua devolvendo `bool`, mas agora só `false` quando
   o gateway de fato recusou a exclusão — erro de rede lança. Quem não tratava
   o array de erro tinha uma falha silenciosa, que na v2 passa a interromper o
   fluxo ([#71]).
2. **`GatewayInterface` reduzido a `name()`.** Quem tipava `GatewayInterface` e
   chamava recursos precisa passar a tipar a capacidade (`SupportsCharges`,
   por exemplo), ou usar a facade `PHPay` ([#73]).
3. **Namespaces unificados.** `PHPay\Gateways\Asaas\*` → `PHPay\Asaas\*`, e
   `Efi\Resources\*`, `Efi\Traits\*`, `Efi\Interface\*` → `PHPay\Efi\*`.
   `PHPay\Asaas\*`, `PHPay\Efi\*` e `PHPay\PHPay` não mudaram ([#71]).
4. **`pix()` não recebe mais argumento** — o array era aceito e descartado em
   toda a cadeia ([#71]).
5. **`messages()` de cobrança e assinatura ficou plano**:
   `AsaasChargeRequest::messages()->charge->customer` virou
   `::messages()->customer`. As mensagens também deixaram de carregar o prefixo
   do gateway no texto — quem prefixa agora é a exceção ([#71]).
6. **`AsaasGateway` perdeu as propriedades públicas `$client` e `$customer`**,
   que eram sem uso e colidiam com o método de mesmo nome. Use os métodos
   ([#71]).
7. **Licença MIT → Business Source License 1.1**, source-available com uma
   única restrição comercial: ninguém pode oferecer o PHPay, ou um derivado,
   como biblioteca/SDK/serviço de integração de pagamentos concorrente. Usar em
   produção, inclusive em software fechado e comercial, continua liberado. Em
   2030-09-20 converte automaticamente para MIT, e cada versão converte no
   máximo quatro anos após ser publicada. **As v1.0.0 e v1.0.1 permanecem sob
   MIT** — licença nova não é retroativa. Contribuições passam a estar sujeitas
   ao CLA ([#80], [#81], [#82]).

E duas mudanças de comportamento que não quebram chamada:

- **Efí: autorização sob demanda.** O construtor de `EfiGateway` fazia uma
  requisição HTTP; agora autoriza na primeira vez que o token é necessário, e
  só uma vez. Construir o gateway deixou de ser uma operação que pode falhar
  ([#71]).
- **`setCustomer()` reaproveita o cliente** quando o array traz `id`, e existe
  `setCustomerId()` para o caminho explícito ([#71]).

### Corrigido

- **A validação de tipo não funcionava.** `AsaasChargeRequest` usava `&&` onde
  deveria usar `||`: um campo presente com o tipo errado passava batido e
  seguia para a API ([#71]).
- **`setCustomer()` criava cliente duplicado** a cada chamada — duas cobranças
  para a mesma pessoa geravam dois cadastros no gateway. Vale auditar
  integrações da v1 que chamavam `setCustomer()` em laço ([#71]).
- **`EfiGateway` fazia chamada de rede no construtor** ([#71]).
- **`Efi\Resources\Charge\Charge::getStatus()`** apontava para
  `payments/{id}/status`, endpoint do Asaas — sempre 404 na base da Efí. Agora
  usa `v1/charge/{id}` ([#71]).
- **`WebhookEventsEnum`** usava namespace fora do autoload e não carregava
  ([#71]).
- **`EfiGateway::customer()` e `webhook()`** devolviam `stdClass` vazio,
  causando "undefined method" adiante ([#71]).
- **`examples/efi/charges.php`** passava `100.00` num gateway que usa centavos —
  cobraria R$ 1,00 ([#71]).

### Removido

- `PHPay\Exceptions\AsaasExceptions` — não era usada, e declarava retornar
  `Exception` num método que lançava ([#71]).
- `PHPay\Gateways\Asaas\Requests\AsaasPixRequest` — não era chamada, e seus
  campos não correspondem a nenhum endpoint de Pix do Asaas ([#71]).
- Os quatro stubs de `NotImplementedException` da Efí, substituídos pelas
  interfaces de capacidade ([#73]).
- Os roots PSR-4 `Efi\` e `AsaasCustomer\` ([#71]).
- O ramo `->customer->id` de `messages()`, que nenhum código lia ([#71]).

## [1.0.1] - 2025-05-08

[Release](https://github.com/phpay-io/phpay/releases/tag/v1.0.1)

### Adicionado

- **Asaas: criação de assinatura** — recurso `Subscription` com `create()`,
  `StoreSubscriptionAsaasRequest`, `subscription()` no `GatewayInterface` e na
  facade, e o exemplo `examples/asaas/subscriptions.php` ([#66]).
- Primeiros testes com Pest, cobrindo a facade e os dois gateways ([#63]).

### Corrigido

- **Asaas: os filtros de `getAll()` de clientes não eram aplicados.** O array
  de filtros ia aninhado numa chave `query` que o trait já montava, então a
  listagem voltava sem filtro nenhum ([#69]).
- Exemplo de clientes do Asaas: import do gateway e construção da facade por
  `PHPay::gateway()` ([#67]).

## [1.0.0] - 2025-02-12

[Release](https://github.com/phpay-io/phpay/releases/tag/v1.0.0) ·
Primeira versão publicada no Packagist, sob licença MIT.

### Adicionado

- Facade `PHPay`, que delega tudo para o gateway injetado no construtor, e
  `GatewayInterface` como contrato comum ([#14], [#24]).
- **Gateway Asaas** — clientes, cobranças (criar, listar, buscar e QR Code),
  webhooks e chaves Pix ([#2], [#5], [#6], [#7], [#8], [#9], [#11], [#12],
  [#28], [#52], [#53], [#58]).
- **Gateway Efí** — autorização por token e cobranças: criar, listar, buscar,
  marcar como paga, cancelar, remover e atualizar vencimento e metadados
  ([#18], [#22], [#29], [#30], [#32], [#33], [#34], [#35], [#41], [#42],
  [#43], [#44], [#49], [#50]).
- Validação de payload em classes `*Request` estáticas, com mensagens em
  português ([#5], [#10]).
- Ambiente de desenvolvimento com Docker e Makefile, Pint (PSR-12), PHPStan
  nível 9 e hooks do Husky ([#1], [#4], [#15], [#17], [#55], [#56]).
- Exemplos manuais em `examples/`, contra o sandbox de cada gateway ([#23],
  [#25], [#42]).

[Não lançado]: https://github.com/phpay-io/phpay/compare/v2.8.0...develop
[2.8.0]: https://github.com/phpay-io/phpay/compare/v2.7.0...v2.8.0
[2.7.0]: https://github.com/phpay-io/phpay/compare/v2.6.0...v2.7.0
[2.6.0]: https://github.com/phpay-io/phpay/compare/v2.5.0...v2.6.0
[2.5.0]: https://github.com/phpay-io/phpay/compare/v2.4.0...v2.5.0
[2.4.0]: https://github.com/phpay-io/phpay/compare/v2.3.0...v2.4.0
[2.3.0]: https://github.com/phpay-io/phpay/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/phpay-io/phpay/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/phpay-io/phpay/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/phpay-io/phpay/compare/v1.0.1...v2.0.0
[1.0.1]: https://github.com/phpay-io/phpay/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/phpay-io/phpay/commits/v1.0.0

[#1]: https://github.com/phpay-io/phpay/pull/1
[#2]: https://github.com/phpay-io/phpay/pull/2
[#4]: https://github.com/phpay-io/phpay/pull/4
[#5]: https://github.com/phpay-io/phpay/pull/5
[#6]: https://github.com/phpay-io/phpay/pull/6
[#7]: https://github.com/phpay-io/phpay/pull/7
[#8]: https://github.com/phpay-io/phpay/pull/8
[#9]: https://github.com/phpay-io/phpay/pull/9
[#10]: https://github.com/phpay-io/phpay/pull/10
[#11]: https://github.com/phpay-io/phpay/pull/11
[#12]: https://github.com/phpay-io/phpay/pull/12
[#14]: https://github.com/phpay-io/phpay/pull/14
[#15]: https://github.com/phpay-io/phpay/pull/15
[#17]: https://github.com/phpay-io/phpay/pull/17
[#18]: https://github.com/phpay-io/phpay/pull/18
[#22]: https://github.com/phpay-io/phpay/pull/22
[#23]: https://github.com/phpay-io/phpay/pull/23
[#24]: https://github.com/phpay-io/phpay/pull/24
[#25]: https://github.com/phpay-io/phpay/pull/25
[#28]: https://github.com/phpay-io/phpay/pull/28
[#29]: https://github.com/phpay-io/phpay/issues/29
[#30]: https://github.com/phpay-io/phpay/pull/30
[#32]: https://github.com/phpay-io/phpay/issues/32
[#33]: https://github.com/phpay-io/phpay/issues/33
[#34]: https://github.com/phpay-io/phpay/issues/34
[#35]: https://github.com/phpay-io/phpay/issues/35
[#41]: https://github.com/phpay-io/phpay/pull/41
[#42]: https://github.com/phpay-io/phpay/pull/42
[#43]: https://github.com/phpay-io/phpay/pull/43
[#44]: https://github.com/phpay-io/phpay/pull/44
[#49]: https://github.com/phpay-io/phpay/issues/49
[#50]: https://github.com/phpay-io/phpay/pull/50
[#52]: https://github.com/phpay-io/phpay/issues/52
[#53]: https://github.com/phpay-io/phpay/pull/53
[#55]: https://github.com/phpay-io/phpay/issues/55
[#56]: https://github.com/phpay-io/phpay/pull/56
[#58]: https://github.com/phpay-io/phpay/pull/58
[#63]: https://github.com/phpay-io/phpay/pull/63
[#66]: https://github.com/phpay-io/phpay/pull/66
[#67]: https://github.com/phpay-io/phpay/pull/67
[#69]: https://github.com/phpay-io/phpay/pull/69
[#70]: https://github.com/phpay-io/phpay/issues/70
[#71]: https://github.com/phpay-io/phpay/pull/71
[#72]: https://github.com/phpay-io/phpay/issues/72
[#73]: https://github.com/phpay-io/phpay/pull/73
[#74]: https://github.com/phpay-io/phpay/issues/74
[#75]: https://github.com/phpay-io/phpay/pull/75
[#76]: https://github.com/phpay-io/phpay/issues/76
[#77]: https://github.com/phpay-io/phpay/pull/77
[#78]: https://github.com/phpay-io/phpay/issues/78
[#79]: https://github.com/phpay-io/phpay/pull/79
[#80]: https://github.com/phpay-io/phpay/issues/80
[#81]: https://github.com/phpay-io/phpay/pull/81
[#82]: https://github.com/phpay-io/phpay/pull/82
[#83]: https://github.com/phpay-io/phpay/issues/83
[#84]: https://github.com/phpay-io/phpay/pull/84
[#85]: https://github.com/phpay-io/phpay/issues/85
[#86]: https://github.com/phpay-io/phpay/pull/86
[#87]: https://github.com/phpay-io/phpay/issues/87
[#88]: https://github.com/phpay-io/phpay/pull/88
[#89]: https://github.com/phpay-io/phpay/issues/89
[#90]: https://github.com/phpay-io/phpay/pull/90
[#91]: https://github.com/phpay-io/phpay/issues/91
[#92]: https://github.com/phpay-io/phpay/pull/92
[#93]: https://github.com/phpay-io/phpay/issues/93
[#94]: https://github.com/phpay-io/phpay/pull/94
[#95]: https://github.com/phpay-io/phpay/issues/95
[#96]: https://github.com/phpay-io/phpay/pull/96
[#97]: https://github.com/phpay-io/phpay/issues/97
[#98]: https://github.com/phpay-io/phpay/pull/98
[#99]: https://github.com/phpay-io/phpay/issues/99
[#100]: https://github.com/phpay-io/phpay/pull/100
[#101]: https://github.com/phpay-io/phpay/issues/101
[#102]: https://github.com/phpay-io/phpay/pull/102
[#109]: https://github.com/phpay-io/phpay/issues/109
[#111]: https://github.com/phpay-io/phpay/issues/111
[#112]: https://github.com/phpay-io/phpay/pull/112
[#113]: https://github.com/phpay-io/phpay/issues/113
[#114]: https://github.com/phpay-io/phpay/pull/114
[#121]: https://github.com/phpay-io/phpay/issues/121
[#124]: https://github.com/phpay-io/phpay/issues/124
[#131]: https://github.com/phpay-io/phpay/issues/131
[#132]: https://github.com/phpay-io/phpay/issues/132
[#133]: https://github.com/phpay-io/phpay/issues/133
[#134]: https://github.com/phpay-io/phpay/issues/134
[#140]: https://github.com/phpay-io/phpay/issues/140
[#143]: https://github.com/phpay-io/phpay/issues/143
[#146]: https://github.com/phpay-io/phpay/issues/146
[#152]: https://github.com/phpay-io/phpay/issues/152
[#156]: https://github.com/phpay-io/phpay/issues/156
