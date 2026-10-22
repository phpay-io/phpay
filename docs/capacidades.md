# Capacidades

`GatewayInterface` carrega só a identidade do gateway. Cada recurso é uma
interface que o gateway implementa **se, e só se,** oferecer:

```php
use PHPay\Contracts\Capability;

$phpay = PHPay::gateway(new RedeGateway(REDE_PV, REDE_TOKEN));

$phpay->name();                                 // 'Rede'
$phpay->supports(Capability::SUBSCRIPTIONS);    // false
$phpay->capabilities();                          // [Capability::CHARGES]
```

Chamar um recurso que o gateway não oferece lança uma exceção que diz o que ele
oferece:

```php
$phpay->pix();
// NotImplementedException: Rede não suporta chaves Pix.
//                          Capacidades disponíveis: cobranças.
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
