# Resolvendo o gateway

Há dois caminhos para começar, e eles servem a situações diferentes.

[← voltar ao índice](README.md)

## Quando você sabe o gateway

```php
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS, sandbox: true));
```

É o caminho a preferir: segurando o gateway concreto, **o PHPStan acusa
capacidade que ele não tem em tempo de análise**, não em runtime — como a
página de [capacidades](capacidades.md) explica.

## Quando o gateway vem de configuração

```php
use PHPay\Support\Gateway;

$phpay = PHPay::make('asaas', ['token' => TOKEN_ASAAS, 'sandbox' => true]);

/* ou, se você quiser o gateway concreto em vez da facade */
$asaas = Gateway::ASAAS->make(['token' => TOKEN_ASAAS]);
```

Para quando a escolha não está no código: um `PHPAY_GATEWAY=asaas` no `.env`,
uma troca de gateway em runtime, um service provider de framework transformando
um array em objeto.

O nome tolera caixa e espaço, porque costuma vir de configuração:
`Gateway::named('  ASAAS ')` é `Gateway::ASAAS`.

## O que cada gateway pede

Os construtores não concordam, e o registry respeita isso em vez de inventar
uma forma única:

| Gateway | Nome | Credenciais | `sandbox` |
| --- | --- | --- | :---: |
| Asaas | `asaas` | `token` | ✅ |
| Woovi/OpenPix | `woovi` | `appId` | ✅ |
| Efí | `efi` | `clientId`, `clientSecret` | ✅ |
| Mercado Pago | `mercadopago` | `accessToken` | — |
| PagBank | `pagbank` | `token` | ✅ |
| Pagar.me | `pagarme` | `secretKey` | — |
| Cielo | `cielo` | `merchantId`, `merchantKey` | ✅ |
| Rede | `rede` | `affiliation`, `secret` | ✅ |
| AbacatePay | `abacatepay` | `token` | — |

`sandbox` é `true` por padrão, como em todo construtor da biblioteca.

A **Efí** aceita `certificate` a mais, com o caminho do `.p12`/`.pem` ou o
value object — a API Pix dela é [só por mTLS](gateways/efi.md):

```php
Gateway::EFI->make([
    'clientId'     => EFI_CLIENT_ID,
    'clientSecret' => EFI_CLIENT_SECRET,
    'certificate'  => '/caminho/certificado.p12',
]);
```

Os três **sem `sandbox`** não são descuido: o [ambiente deles vem do prefixo da
credencial](ambientes-e-credenciais.md) — `TEST-` no Mercado Pago, `sk_test_`
no Pagar.me — e a AbacatePay não tem prefixo nenhum.

## Erro de configuração falha na construção

Nada é ignorado em silêncio:

```php
PHPay::make('xpto', []);
// ValidationException: Não existe gateway "xpto".
//                      Disponíveis: asaas, woovi, efi, mercadopago, ...

PHPay::make('asaas', []);
// ValidationException: Informe a chave "token" na configuração, como string
//                      não vazia. Chaves aceitas: token, sandbox.

PHPay::make('asaas', ['token' => $t, 'skandbox' => true]);
// ValidationException: A chave "skandbox" não é aceita na configuração deste
//                      gateway. Chaves aceitas: token, sandbox.

PHPay::make('mercadopago', ['accessToken' => $t, 'sandbox' => true]);
// ValidationException: A chave "sandbox" não é aceita neste gateway: o ambiente
//                      dele vem do prefixo da credencial, não de um parâmetro.
```

As duas últimas merecem explicação. **Chave desconhecida é erro, não é
ignorada**: um `skandbox` engolido é um typo que só aparece em produção. E
mandar `sandbox` para um gateway que não o aceita revela que quem escreveu acha
que aquilo troca o ambiente — e não troca.

## O que o registry não faz

- **Não lê `.env` nem arquivo de config.** A biblioteca recebe array; de onde
  ele vem é da aplicação.
- **Não recebe `Client`.** Client injetado é coisa de teste, e teste constrói
  direto.
- **Não registra gateway de fora.** Gateway privado continua sendo construído
  com `new` e passado para `PHPay::gateway()`.
