# Capacidades

`GatewayInterface` carrega só a identidade do gateway. Cada recurso é uma
interface que o gateway implementa **se, e só se,** oferecer:

```php
use PHPay\Contracts\Capability;

$phpay = PHPay::gateway(new RedeGateway(REDE_PV, REDE_TOKEN));

$phpay->name();                                 // 'Rede'
$phpay->supports(Capability::SUBSCRIPTIONS);    // false
$phpay->capabilities();                          // [Capability::CHARGES, Capability::REFUNDS]
```

Chamar um recurso que o gateway não oferece lança uma exceção que diz o que ele
oferece:

```php
$phpay->pix();
// NotImplementedException: Rede não suporta chaves Pix.
//                          Capacidades disponíveis: cobranças, estornos.
```

Se você segurar o **gateway concreto** em vez da facade, o erro sobe para tempo
de análise — o PHPStan acusa que o método não existe naquele tipo:

```php
$rede = new RedeGateway(REDE_PV, REDE_TOKEN);

$rede->charge();   // ✅
$rede->pix();      // ❌ o método não existe nesse gateway
```

Para injeção de dependência, tipe a capacidade em vez do gateway:

```php
use PHPay\Contracts\SupportsCharges;

public function __construct(private SupportsCharges $gateway) {}
```

## Duas capacidades executam, em vez de devolver recurso

Seis capacidades devolvem um **recurso** — `customer()`, `charge()`, `pix()`,
`subscription()`, `webhook()`, `webhookSignature()`. O estorno e a leitura de
webhook **executam a operação**:

```php
$phpay->refund($id);              // devolve o dinheiro
$phpay->webhookEvents($corpo);    // lê a notificação
```

No estorno:

```php
$phpay->refund($id);          // devolve tudo
$phpay->refund($id, 2500);    // devolve parte, na unidade daquele gateway
```

É de propósito. O que divergia entre os gateways nunca foi como chegar à
cobrança — era a chamada: três nomes para a mesma coisa (`refund`, `cancel`,
`void`), três unidades e três significados para valor ausente. Devolver um
recurso deixaria quem chama adivinhando o nome do método em cada gateway, que é
exatamente a divergência que a capacidade existe para fechar.

`null` no valor estorna integral em todos. Um número cru é lido na unidade que
aquele gateway sempre esperou — centavos em sete deles, reais no Asaas e no
Mercado Pago —, e `Money` tira a dúvida:

```php
use PHPay\Support\Money;

$phpay->refund($id, Money::reais(25.50));   // o mesmo valor nos dez
```

A [leitura de webhook](webhooks.md) é igual: o que divergia era a leitura, não
um handle para alcançar. Ela devolve **lista** de eventos, nunca um — a API Pix
da Efí manda vários pagamentos numa notificação só.
