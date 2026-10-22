# Ambientes e credenciais

Os gateways discordam sobre como separar teste de produção, e o PHPay segue o
que cada um faz em vez de inventar um padrão:

| Gateway | Como o ambiente é decidido |
| --- | --- |
| **Asaas** | `$sandbox` no construtor — troca a URL |
| **PagBank** | `$sandbox` no construtor — troca a URL |
| **Cielo** | `$sandbox` no construtor — troca **as duas** URLs |
| **Efí** | `$sandbox` no construtor — troca **as duas** URLs (Cobranças e Pix) |
| **Rede** | `$sandbox` no construtor — troca **as duas** URLs e o caminho do token |
| **Mercado Pago** | Prefixo do token (`TEST-`); host único, sem `$sandbox` |
| **Pagar.me** | Prefixo da chave (`sk_test_`); host único, sem `$sandbox` |
| **Woovi** | `$sandbox` no construtor — o sandbox tem **domínio próprio** |
| **AbacatePay** | Pela chave usada; host único, **sem prefixo** — a cobrança informa em `devMode` |

Nos dois últimos, `isSandbox()` diz em qual ambiente você está:

```php
(new MercadoPagoGateway($token))->isSandbox();
(new PagarMeGateway($secretKey))->isSandbox();
```

> **Nunca** versione credenciais. Os arquivos `examples/*/credentials.php` são
> ignorados pelo git por padrão.

`var_dump()` e `print_r()` de um gateway ou recurso mostram `********` no lugar
da credencial, e o client HTTP, que a carrega nos headers, aparece oculto. O
`dd()` do Symfony VarDumper lê as propriedades por conta própria e **não** passa
por essa máscara: não despeje o gateway em log.

Toda requisição tem prazo: 60 segundos no total e 10 para conectar. Sem isso, um
gateway que trava prenderia o worker indefinidamente. Um estouro de prazo chega
como `ApiException` com `isConnectionError()` verdadeiro.
