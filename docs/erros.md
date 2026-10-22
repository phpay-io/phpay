# Tratamento de erros

Toda exceção da biblioteca implementa `PHPay\Exceptions\PHPayException`, então
um `catch` cobre a integração inteira:

| Exceção | Estende | Quando acontece |
| --- | --- | --- |
| `ValidationException` | `InvalidArgumentException` | Payload inválido, **antes** de qualquer HTTP |
| `ApiException` | `RuntimeException` | O gateway recusou, ou está inacessível |
| `NotImplementedException` | `BadMethodCallException` | O gateway não oferece o recurso |

```php
use PHPay\Exceptions\ApiException;
use PHPay\Exceptions\PHPayException;
use PHPay\Exceptions\ValidationException;

try {
    $cobranca = $phpay->charge()->setCharge($dados)->create();
} catch (ValidationException $e) {
    // payload inválido: nenhuma requisição foi feita
} catch (ApiException $e) {
    $e->getStatusCode();       // 400, 401, 404… ou 0 se nem chegou ao gateway
    $e->getResponse();         // corpo devolvido pelo gateway
    $e->getGateway();          // 'Asaas', 'Pagar.me', …
    $e->isConnectionError();   // true em timeout, DNS, TLS — vale retry
} catch (PHPayException $e) {
    // qualquer outra falha do PHPay
}
```

`ApiException` já resume os formatos de erro de cada gateway, então
`getMessage()` traz a descrição legível, não um dump.
