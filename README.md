![capa-redes](https://github.com/user-attachments/assets/20051d3a-ecbf-4d01-8c29-50c43b8d3af4)

<p align="center">
    <a href="https://github.com/phpay-io/phpay/releases"><img src="https://poser.pugx.org/phpay-io/phpay/v/stable" alt="Versão estável"></a>
    <a href="https://www.php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.1-brightgreen.svg" alt="Versão do PHP"></a>
    <a href="https://packagist.org/packages/phpay-io/phpay"><img src="https://poser.pugx.org/phpay-io/phpay/downloads" alt="Downloads"></a>
    <a href="https://github.com/phpay-io/phpay/actions/workflows/tests.yml"><img src="https://github.com/phpay-io/phpay/actions/workflows/tests.yml/badge.svg?branch=develop" alt="Testes"></a>
    <a href="./LICENSE.md"><img src="https://img.shields.io/badge/licen%C3%A7a-BUSL--1.1-blue.svg" alt="Licença"></a>
</p>

<p align="center">
    Uma interface só para os gateways de pagamento brasileiros.
</p>

---

## Sumário

- [Por que o PHPay](#por-que-o-phpay)
- [Gateways suportados](#gateways-suportados)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Início rápido](#início-rápido)
- [Documentação](#documentação)
- [Exemplos executáveis](#exemplos-executáveis)
- [Migrando da v1](#migrando-da-v1)
- [Roadmap](#roadmap)
- [Contribuindo](#contribuindo)
- [Segurança](#segurança)
- [Licença](#licença)

---

## Por que o PHPay

Cada gateway brasileiro resolve os mesmos problemas de um jeito diferente: um
chama de `payment`, outro de `order`, outro de `charge`. Um quer reais, outro
quer centavos. Um separa ambiente por URL, outro pelo prefixo do token.

O PHPay normaliza isso numa interface só, **sem esconder o que é genuinamente
diferente**. Quando um gateway não oferece um recurso, ele não finge que
oferece — ele declara o que sabe fazer, e você descobre em tempo de análise
estática, não em produção.

```php
use PHPay\Asaas\AsaasGateway;
use PHPay\PHPay;

$phpay = PHPay::gateway(new AsaasGateway(TOKEN));

$phpay->charge()->setCharge($cobranca)->setCustomerId($clienteId)->create();
```

Trocar de gateway é trocar a linha do construtor.

---

## Gateways suportados

| Gateway | Clientes | Cobranças | Assinaturas | Webhooks | Webhook assinado | Chaves Pix |
| --- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Asaas** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Woovi/OpenPix** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Efí** | — | ✅ | ✅ | ✅ | — | ✅ |
| **Mercado Pago** | ✅ | ✅ | ✅ | — | ✅ | — |
| **PagBank** | ✅ | ✅ | ✅ | — | ✅ | — |
| **Pagar.me** | ✅ | ✅ | ✅ | — | — | — |
| **AbacatePay** | ✅ | ✅ | — | — | ✅ | — |
| **Cielo** | — | ✅ | ✅ | — | — | — |
| **Rede** | — | ✅ | — | — | — | — |

As interfaces correspondentes são `SupportsCustomers`, `SupportsCharges`,
`SupportsSubscriptions`, `SupportsWebhooks`, `SupportsWebhookSignature` e
`SupportsPixKeys`.

Três colunas merecem explicação, porque a ausência de ✅ **não** quer dizer que o
gateway não aceita Pix, não manda webhook ou não assina nada:

- **`SupportsPixKeys`** significa *gerenciar chaves Pix e QR Code estático*, o
  que só um PSP que emite chave própria oferece. Nos outros gateways, Pix é
  forma de pagamento de uma cobrança — e todos aceitam. As duas metades não
  andam juntas em todo PSP: o QR Code estático está no Asaas e na Woovi, que
  expõem endpoint para ele, e **não** na [Efí](docs/gateways/efi.md), cuja API Pix não tem
  essa rota. Onde o gateway não expõe, a biblioteca compõe: ver
  [QR Code estático](docs/qr-code-estatico.md).
- **`SupportsWebhooks`** significa *cadastrar endpoints pela API*. Nos outros,
  o cadastro é no painel; a notificação vai por cobrança, no campo
  `notification_url`. O Pagar.me ainda deixa **consultar e reenviar entregas**,
  através de [`webhookDeliveries()`](docs/gateways/pagarme.md#consultando-entregas-de-webhook).
- **`SupportsWebhookSignature`** significa *provar que a notificação recebida
  veio do gateway*. É outra coisa que cadastrar endpoint, e as duas não andam
  juntas: Mercado Pago, PagBank e AbacatePay assinam sem deixar cadastrar pela
  API, enquanto Efí, Cielo, Rede e Pagar.me não dão o que verificar — a Efí
  notifica por mTLS, Cielo e Rede não mandam assinatura, e a v5 do Pagar.me não
  documenta esquema. O motivo de cada um está em
  [Verificando o webhook que chega](docs/webhooks.md).

---

## Requisitos

| | |
| --- | --- |
| **Para usar a biblioteca** | PHP `^8.1`, `ext-curl`, `ext-json`, `ext-openssl` |
| **Para desenvolver o PHPay** | PHP `^8.2` (Pest 3 e Termwind 2 exigem) |

A compatibilidade com PHP 8.1 é verificada de duas formas a cada build: o
PHPStan analisa `src/` com `phpVersion` mínimo em 8.1, e um job do CI roda no
PHP 8.1 de verdade — valida o `composer.json`, instala só as dependências de
runtime (`--no-dev`), confere a sintaxe de `src/` e roda um smoke test que
carrega o autoload e instancia os nove gateways. A suíte do Pest não roda
nessa versão porque o toolchain de dev exige 8.2+.

---

## Instalação

```bash
composer require phpay-io/phpay
```

---

## Início rápido

Uma cobrança Pix no Asaas, do zero:

```php
use PHPay\Asaas\AsaasGateway;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

try {
    $cobranca = $phpay->charge()
        ->setCharge([
            'billingType' => 'PIX',
            'value'       => 100.50,
            'dueDate'     => date('Y-m-d', strtotime('+3 days')),
            'description' => 'Assinatura PHPay',
        ])
        ->setCustomer([
            'name'    => 'Mário Lucas',
            'cpfCnpj' => '12345678901',
        ])
        ->create();

    print_r($phpay->charge()->getQrCodePix($cobranca['id']));
} catch (PHPayException $e) {
    echo $e->getMessage();
}
```

Um array devolvido é **sempre** uma resposta de sucesso. Qualquer falha vira
exceção — veja [Tratamento de erros](docs/erros.md).

---

## Documentação

A documentação completa está em [`docs/`](docs/). O que vale para todos os
gateways está nos conceitos; cada página de gateway cobre só o que é
específico dele.

**Conceitos**

| | |
| --- | --- |
| [Capacidades](docs/capacidades.md) | o que cada gateway declara suportar, e o que acontece quando você pede o que ele não tem |
| [Resolvendo o gateway](docs/resolvendo-o-gateway.md) | `PHPay::gateway()` quando você sabe qual, `PHPay::make()` quando ele vem de configuração |
| [Tratamento de erros](docs/erros.md) | array é sucesso, falha é exceção — e qual exceção |
| [Retry e idempotência](docs/retry-e-idempotencia.md) | o que a biblioteca repete, o que ela recusa a repetir, e por quê |
| [Testando](docs/testando.md) | `ClientFactory::fake()`, para quem usa a biblioteca testar sem sair para a rede |
| [Ambientes e credenciais](docs/ambientes-e-credenciais.md) | sandbox e produção em cada gateway, e como a credencial fica fora de dump |
| [Cliente](docs/cliente.md) | o VO `Customer`, porque o mesmo campo tem seis grafias entre os gateways |
| [Valores monetários](docs/valores-monetarios.md) | `Money`, porque reais e centavos não se misturam |
| [Status de pagamento](docs/status-de-pagamento.md) | `PaymentStatus`, porque "paga" tem dez grafias entre os gateways |
| [Verificando o webhook que chega](docs/webhooks.md) | provar que a notificação veio do gateway |
| [QR Code estático](docs/qr-code-estatico.md) | compor o BR Code de uma chave Pix localmente, que é como o padrão manda |

**Gateways**

| | | |
| --- | --- | --- |
| [Asaas](docs/gateways/asaas.md) | [Woovi/OpenPix](docs/gateways/woovi.md) | [Efí](docs/gateways/efi.md) |
| [Mercado Pago](docs/gateways/mercado-pago.md) | [PagBank](docs/gateways/pagbank.md) | [Pagar.me](docs/gateways/pagarme.md) |
| [AbacatePay](docs/gateways/abacatepay.md) | [Cielo](docs/gateways/cielo.md) | [Rede](docs/gateways/rede.md) |

---

## Exemplos executáveis

O diretório [`examples/`](./examples) traz scripts prontos por gateway. Copie o
`credentials.example.php` para `credentials.php`, preencha, e rode:

```bash
php examples/asaas/charges.php
```

Dois gateways têm também uma **checagem de conformidade**, que roda contra o
sandbox de verdade e relata cada operação. Teste com HTTP mockado prova que a
biblioteca monta o payload que decidimos; isto prova que o gateway o aceita:

```bash
MP_ACCESS_TOKEN='TEST-...' php examples/mercadopago/sandbox-check.php
PAGBANK_TOKEN='...'        php examples/pagbank/sandbox-check.php
```

Os dois recusam credenciais de produção e nunca imprimem o token.

---

## Migrando da v1

A v2.0.0 tem breaking changes — a principal é que falhas passaram a ser exceção
em vez de array de erro. O de-para completo, quebra por quebra, está em
**[UPGRADE.md](./UPGRADE.md)**.

Dois pontos merecem auditoria de quem vem da v1:

1. Falhas que antes voltavam como array e passavam despercebidas agora
   **interrompem o fluxo**. É o comportamento correto, mas expõe caminhos que
   nunca foram exercitados.
2. Integrações que chamavam `setCustomer()` em laço **provavelmente acumularam
   clientes duplicados** no gateway.

---

## Roadmap

### Plataforma

| Item | Status |
| --- | :---: |
| Definições de arquitetura | ✅ |
| Capacidades por gateway | ✅ |
| Tratamento de erros por exceção | ✅ |
| Testes com HTTP mockado | ✅ |
| CI no GitHub Actions | ✅ |
| Guia de migração | ✅ |
| Documentação | ✍️ |
| Site | 🕛 |

### Cobertura por gateway

| Gateway | Cobranças | Estornos | Clientes | Assinaturas | Webhooks | Pix |
| --- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Asaas** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Woovi/OpenPix** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Mercado Pago** | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| **PagBank** | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| **Pagar.me** | ✅ | ✅ | ✅ | ✅ | leitura ✅ | ✅ |
| **AbacatePay** | ✅ | 🕥 | ✅ | — | — | ✅ |
| **Cielo** | ✅ | ✅ | — | ✅ | — | ✅ |
| **Rede** | ✅ | ✅ | — | — | — | 🕥 |
| **Efí** | ✅ | só Pix ✅ | — | ✅ | ✅ | ✅ |

**✅** pronto · **✍️** parcial · **🕥** planejado · **—** não existe na API do gateway

A **leitura de webhook** (`webhookEvents()`) fica em sete gateways: Asaas,
Woovi, Pagar.me, PagBank, Efí, Mercado Pago e Cielo. Rede e AbacatePay ficam
fora porque o formato de notificação das duas não está confirmado — detalhes em
[docs/webhooks.md](docs/webhooks.md).

O estorno da **Efí** é a devolução Pix, alcançável em `pixCharge()->refund()`: a
API dela exige o valor e endereça pelo `endToEndId`, então fica fora da
capacidade portável. Na **AbacatePay**, os endpoints de reembolso documentados
são da v2 e de outra família de recursos — a biblioteca fala com a v1.

---

## Contribuindo

Leia o [manual de contribuição](./CONTRIBUTING.md). Ele cobre o ambiente de
desenvolvimento, o gate de qualidade e as convenções do projeto.

Contribuições enviadas a partir de 2026-09-20 estão sujeitas ao
[Contributor License Agreement](./CLA.md), aceito por checkbox no pull request.

```bash
composer install
composer test     # Pint + Pest + PHPStan nível 9
```

Nenhum teste pode acessar a rede: os recursos aceitam um `GuzzleHttp\Client`
injetado, e a suíte usa mocks.

---

## Segurança

Encontrou uma vulnerabilidade? Não abra issue pública — siga a
[política de segurança](./.github/SECURITY.md).

Esta é uma biblioteca de pagamentos: nunca logue, imprima ou versione tokens,
`access_token`, `clientSecret` ou CPF/CNPJ reais.

---

## Licença

O PHPay é distribuído sob a **[Business Source License 1.1](./LICENSE.md)** a
partir da versão 2.0.0. É uma licença *source-available*: o código é aberto e
auditável, com uma única restrição comercial.

**O resumo abaixo não substitui a licença** — ele existe só para você saber
rápido se precisa ler o texto completo.

| | |
| --- | --- |
| ✅ **Pode** | Usar em produção, inclusive em software fechado e comercial |
| ✅ **Pode** | Processar pagamentos seus ou dos seus clientes |
| ✅ **Pode** | Modificar, forkar, estudar e redistribuir |
| ❌ **Não pode** | Oferecer o PHPay, ou um derivado, como biblioteca, SDK ou serviço de integração de pagamentos que concorra com ele |

Ou seja: se você integra pagamentos **no seu produto**, nada muda para você. A
restrição atinge apenas quem quiser revender o próprio PHPay.

Em **2030-09-20** a licença converte automaticamente para **MIT**, e cada
versão converte no máximo quatro anos após ser publicada.

> As versões **1.0.0 e 1.0.1** foram publicadas sob MIT e **permanecem sob
> MIT** — uma licença nova não é retroativa. O texto está preservado em
> [LICENSE-MIT.md](./LICENSE-MIT.md).

Precisa de termos diferentes? Escreva para [fale@phpay.io](mailto:fale@phpay.io).

---

<p align="center">
    Feito por <a href="https://github.com/mariolucasdev">Mário Lucas</a> ·
    <a href="mailto:fale@phpay.io">fale@phpay.io</a>
</p>
