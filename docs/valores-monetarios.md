# Unidade monetária

Os gateways discordam sobre a unidade, e **errar não quebra a integração — ela
cobra o valor errado**. Mandar `100.50` num gateway de centavos cobra R$ 1,00,
e você só descobre na conciliação.

Use `Money` e o problema deixa de existir: você diz a unidade que tem, o
gateway pede a unidade que precisa, e nenhum dos dois pode errar.

```php
use PHPay\Support\Money;

$valor = Money::reais(100.50);     // ou Money::centavos(10050)

$asaas->charge()->setAmount($valor);          // vira 100.50
$pagbank->charge()->addItem('Item', $valor);  // vira 10050
```

Também aceita string, que é o que um campo de formulário costuma entregar:

```php
Money::reais('100,50');      // notação brasileira
Money::reais('1.234,56');    // com separador de milhar
Money::reais('100.50');      // notação com ponto
```

E tem o que um total precisa:

```php
$unitario = Money::reais(59.90);

$unitario->multiply(2);              // R$ 119,80
$unitario->plus(Money::reais(10));   // R$ 69,90
$unitario->format();                 // 'R$ 59,90'
```

> `Money::reais(10 / 3)` **lança exceção** em vez de arredondar. Arredondamento
> silencioso é como nascem erros de um centavo na conciliação — arredonde você
> mesmo, ou use `Money::centavos()` para ser exato.

## Passando número cru

Continua funcionando, e cada gateway lê na unidade que sempre esperou:

| Gateway | Unidade do número cru | R$ 100,50 |
| --- | --- | --- |
| **Asaas**, **Mercado Pago** | Reais (decimal) | `100.50` |
| **PagBank**, **Pagar.me**, **Cielo**, **Rede**, **AbacatePay**, **Woovi**, **Efí** | Centavos (inteiro) | `10050` |

Nos que usam centavos, o PHPay recusa decimal na validação. Mas é justamente
essa tabela que o `Money` torna desnecessária — **prefira o value object**.

A exceção é a [API Pix do Efí](gateways/efi.md#api-pix), que **não aceita número cru, só
`Money`**. Ela quer reais (`"100.50"`) enquanto a API de Cobranças do mesmo
gateway quer centavos, e um número solto ali seria a ambiguidade que o value
object existe para eliminar.
