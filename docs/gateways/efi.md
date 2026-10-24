# Efí

**Duas APIs com as mesmas credenciais**, e é isso que dá ao Efí quatro das
cinco capacidades:

| API | Host | Autenticação | Recursos |
| --- | --- | --- | --- |
| **Cobranças** | `cobrancas.api.efipay.com.br` | OAuth2 | `charge()` — boleto |
| **Pix** | `pix.api.efipay.com.br` | OAuth2 **+ mTLS** | `pix()`, `webhook()`, `subscription()`, `pixCharge()` |

Clientes ficam de fora: nenhuma das duas APIs mantém cadastro de cliente.

O gateway **não faz chamada de rede no construtor**. Cada API tem o seu token,
pedido na primeira vez que é necessário e **renovado sozinho quando expira** —
um gateway vivo num worker de fila não passa a tomar 401.

## Cobranças (boleto)

```php
use PHPay\Efi\EfiGateway;
use PHPay\Support\{Customer, Money};

$gateway = new EfiGateway(CLIENT_ID, CLIENT_SECRET);

$cobranca = PHPay::gateway($gateway)->charge([
    'description' => 'Assinatura PHPay',
    'expire_at'   => date('Y-m-d', strtotime('+3 days')),
])
    ->setAmount(Money::reais('100,50'))
    ->setCustomer(new Customer('Mário Lucas', '12345678909'))
    ->create();
```

## API Pix

**Toda requisição da API Pix é por mTLS**, inclusive a do token. Passe o
certificado `.p12` (ou `.pem`) da aplicação, que você baixa no painel do Efí:

```php
$gateway = new EfiGateway(CLIENT_ID, CLIENT_SECRET, certificate: '/caminho/certificado.p12');
```

Com senha, ou vindo de uma variável de ambiente — o comum em container e
serverless:

```php
use PHPay\Http\Certificate;

new EfiGateway(CLIENT_ID, CLIENT_SECRET, certificate: new Certificate('/caminho/certificado.p12', 'senha'));

new EfiGateway(CLIENT_ID, CLIENT_SECRET, certificate: Certificate::fromBase64(getenv('EFI_CERTIFICATE_BASE64')));
```

> O `fromBase64()` grava o certificado num arquivo temporário com permissão
> `0600`, apagado quando o processo termina. A senha nunca aparece num
> `var_dump()`.

O Efí entrega certificado e chave num arquivo só. Os formatos aceitos, o par
`.crt` + `.key` que outros PSPs entregam e o que o `Certificate` confere estão
em [Certificado e mTLS](../mtls.md).

Certificado de homologação só funciona com `$sandbox = true`, e o de produção,
com `false`. Sem certificado, a API de Cobranças continua funcionando e a API
Pix responde com uma `ValidationException` clara, não com um erro de TLS.

**Cobrança Pix** — imediata por padrão, com vencimento quando há `setDueDate()`:

```php
$cobranca = $gateway->pixCharge()
    ->setAmount(Money::reais('123,45'))
    ->setKey('sua-chave-pix')                       // chave da conta Efí que recebe
    ->setCustomer(new Customer('Mário Lucas', '12345678909'))
    ->setDescription('Pedido 1234')
    ->setExpiration(3600)                           // segundos
    ->create();

$qr = $gateway->pixCharge()->qrCode($cobranca['loc']['id']);
$qr['qrcode'];         // copia e cola
$qr['imagemQrcode'];   // PNG em base64

/* com vencimento: multa, juros e desconto como num boleto */
$gateway->pixCharge()
    ->setAmount(Money::reais(250))
    ->setKey('sua-chave-pix')
    ->setCustomer(new Customer('Sixtec LTDA', '12345678000199'))
    ->setDueDate('2026-12-31', validityAfterDue: 15)
    ->create();

/* devolução, total ou parcial */
$gateway->pixCharge()->refund($endToEndId, Money::reais(10));
```

`pixCharge()` é um **extra do gateway concreto**, como o `webhookDeliveries()`
do Pagar.me: o `charge()` da facade já é o boleto, e mudar o retorno dele
quebraria quem está na v2.

Esse `refund()` **não é a capacidade `SupportsRefunds`**, e a Efí não a declara.
São três incompatibilidades, todas da API dela e não da biblioteca: o valor é
**obrigatório** (o BACEN exige `valor` na devolução, enquanto a capacidade
promete que valor ausente estorna integral), o endereçamento é pelo
`endToEndId` do Pix recebido e não pela cobrança, e a devolução vive na API Pix
— o `charge()` da Efí é boleto, que não estorna por aqui. Declarar a capacidade
e depois recusar de dentro dela seria pior que não declarar.

**Chaves Pix** — só chaves aleatórias (EVP) são gerenciáveis pela API:

```php
$chave = PHPay::gateway($gateway)->pix()->createKey()['chave'];

PHPay::gateway($gateway)->pix()->getAll();
PHPay::gateway($gateway)->pix()->destroy($chave);
```

> **Sem QR Code estático.** A API Pix da Efí não expõe endpoint para criar um,
> então aqui não existe o `staticQrCode()` do Asaas e da Woovi. Pelo padrão do
> BACEN, um QR Code estático é um BR Code composto a partir da chave, de um
> valor opcional e de um identificador livre — quem oferece endpoint faz por
> conveniência, não por necessidade. O `qrCode()` do `pixCharge()`, acima, é o
> QR Code **de uma cobrança**, que é outra coisa.
>
> Para o estático, use [`StaticPixCode`](../qr-code-estatico.md): ele compõe o
> payload a partir da chave, sem rede e sem gateway.

**Webhooks** — um por chave Pix, endereçado pela própria chave:

```php
PHPay::gateway($gateway)
    ->webhook(['chave' => $chave, 'webhookUrl' => 'https://loja.com/webhook/pix'])
    ->create();
```

> O mTLS vale **nos dois sentidos**: por padrão o Efí só entrega para um
> servidor que valide o certificado dele. Se o seu não consegue (hospedagem
> compartilhada, balanceador que termina o TLS), `skipMtlsChecking()` desliga a
> checagem — e aí valide a origem de outro jeito, como um `hmac` na URL.

**Pix Automático** — o pagador autoriza uma vez no app do banco, e cada ciclo
é debitado sem nova aprovação:

```php
use PHPay\Efi\Enums\{AccountTypeEnum, PeriodicityEnum};

$assinaturas = PHPay::gateway($gateway)->subscription();

/* 1. o location que o QR Code de autorização aponta */
$location = $assinaturas->createLocation();

/* 2. a recorrência: o que o pagador autoriza */
$recorrencia = $assinaturas
    ->setCustomer(new Customer('Mário Lucas', '12345678909'))
    ->setContract('CONTRATO-2026-001')              // até 35 caracteres
    ->setDescription('Plano mensal')
    ->setAmount(Money::reais('49,90'))               // ou setMinimumAmount(), para valor variável
    ->setPeriodicity(PeriodicityEnum::MONTHLY, '2026-10-01')
    ->allowRetries()                                 // até 3 tentativas em 7 dias
    ->setLocation($location['id'])
    ->create();

$assinaturas->find($recorrencia['idRec'])['dadosQR'];   // copia e cola para o pagador autorizar

/* 3. a cobrança de cada ciclo */
$assinaturas
    ->setReceiver('12345-6', AccountTypeEnum::CHECKING, '0001')
    ->createCharge($recorrencia['idRec'], Money::reais('49,90'), '2026-11-05');

$assinaturas->cancel($recorrencia['idRec']);
```
