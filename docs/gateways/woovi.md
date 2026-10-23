# Woovi/OpenPix

**O segundo gateway com as cinco capacidades**, ao lado do Asaas — e o que
confirma que o modelo descreve o domínio, não um fornecedor: são duas empresas
independentes, com APIs independentes, preenchendo o mesmo contrato.

Sendo PSP Pix-nativo, ele gerencia chaves e QR Code estático de verdade.

```php
use PHPay\Woovi\Enums\PixKeyTypeEnum;
use PHPay\Woovi\WooviGateway;

$phpay = PHPay::gateway(new WooviGateway(WOOVI_APP_ID));

/* chaves Pix da conta */
$phpay->pix()->createKey(PixKeyTypeEnum::RANDOM);
$phpay->pix()->getAll();

/* consulta uma chave antes de pagar */
$phpay->pix()->verifyKey('fale@phpay.io');

/* QR Code estático, com ou sem valor */
$phpay->pix()->staticQrCode('Caixa 1');
$phpay->pix()->staticQrCode('Caixa 2', 2500);
```

Três particularidades:

**O AppID vai cru no `Authorization`** — sem `Bearer`, sem `Basic`.

**O sandbox tem domínio próprio**: `api.woovi-sandbox.com` contra
`api.openpix.com.br`.

**Todo objeto é endereçável pelo `correlationID`**, o id no *seu* sistema —
nenhum outro gateway da biblioteca oferece isso:

```php
$cobranca = $phpay->charge()
    ->setCorrelationId('pedido-1')
    ->setCustomer(['name' => 'Mário Lucas', 'email' => 'fale@phpay.io'])
    ->create(10050);   // R$ 100,50

$phpay->charge()->getPixCode($cobranca);
$phpay->charge()->find('pedido-1');      // pelo SEU id, não pelo do gateway
```

Webhooks têm CRUD por API — junto com o Asaas, os únicos:

```php
$phpay->webhook(['name' => 'PHPay', 'url' => 'https://exemplo.test/webhook'])->create();
$phpay->webhook()->getAll();
```

> Repare que o webhook fica em `api/openpix/v1/`, enquanto os demais recursos
> ficam em `api/v1/` — herança da fusão das duas marcas. O PHPay trata isso
> internamente.

## Estorno

```php
$phpay->refund('pedido-1');                      // devolve tudo
$phpay->refund('pedido-1', 2500);                // devolve R$ 25,00
$phpay->charge()->refund('pedido-1', 2500, 'meu-estorno-1');
```

> **Dois `correlationID` se encontram nesta chamada, e eles não são a mesma
> coisa.** O da URL endereça a **cobrança**; o do corpo é um id de
> idempotência do **estorno**, que o PHPay gera com
> `PHPay\Http\Idempotency::key()` quando você não informa. Mandar o id da
> cobrança no corpo faria a Woovi deduplicar contra o objeto errado.

Omitir o valor devolve o total da cobrança, ou o que ainda não foi devolvido
dela. Valores em centavos, como em todo este gateway.
