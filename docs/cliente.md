# Cliente

O mesmo campo tem **seis grafias** entre os gateways: `cpfCnpj` no Asaas,
`tax_id` no PagBank, `document` no Pagar.me, `taxId` no AbacatePay, `taxID` no
Woovi, `cpf_cnpj` no Efí. Código escrito para um não migra para outro, e nada
no tipo avisa.

`Customer` é a forma única. Cada gateway mapeia para o formato dele:

```php
use PHPay\Support\Customer;

$cliente = Customer::make(
    name: 'Mário Lucas',
    document: '123.456.789-01',     // pontuação é limpa
    email: 'fale@phpay.io',
    phone: '(11) 94002-8922',
);

$phpay->charge()->setCustomer($cliente);   // funciona nos nove
```

Ele também carrega o que cada gateway deriva do cliente, e que antes ficava
espalhado:

```php
$cliente->isIndividual();   // CPF: o Pagar.me precisa como type: 'individual'
$cliente->documentType();   // 'CPF' | 'CNPJ': a Cielo quer em IdentityType
$cliente->firstName();      // o Mercado Pago quer nome e sobrenome separados
$cliente->phoneParts();     // ['country' => '55', 'area' => '11', ...] para o PagBank
```

Para um cliente que já existe no gateway, ou para campos que só aquele gateway
tem:

```php
$cliente->withId('cus_000006337812');            // reaproveita em vez de criar
$cliente->withExtra(['externalReference' => 'x']); // vai junto no payload
```

> O `withExtra()` existe para o que é específico de um gateway — endereço,
> data de nascimento, referência externa. **Nenhum campo obrigatório de
> nenhum dos nove gateways precisa dele**: o value object cobre todos.
