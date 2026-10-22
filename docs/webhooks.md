# Verificando o webhook que chega

Cadastrar o endpoint é metade do caminho. A outra é provar que a notificação
veio do gateway: sem isso, quem descobrir a URL confirma uma cobrança. Cada
gateway assina de um jeito, e `webhookSignature()` dá a todos a mesma forma.

```php
$ok = $gateway->webhookSignature()
    ->setSecret($segredoDoWebhook)
    ->setPayload(file_get_contents('php://input'))
    ->setHeaders(getallheaders())
    ->verify();

if (!$ok) {
    http_response_code(401);
    exit;
}
```

`verify()` devolve `bool` — assinatura que não bate é resposta, não erro. O que
levanta `ValidationException` é uso errado: segredo ausente, corpo vazio ou
header de assinatura que não chegou.

**O corpo precisa ser o cru.** `file_get_contents('php://input')`, nunca o array
decodificado e reserializado: um espaço a mais muda o hash. Em Laravel,
`$request->getContent()`.

| Gateway | O que é verificado | O que isso prova |
| --- | --- | --- |
| Asaas | token no header `asaas-access-token` | que o remetente conhece o token — o corpo não é assinado |
| PagBank | `x-authenticity-token`: SHA-256 de `{token}-{corpo}` | que o corpo chegou inteiro |
| Mercado Pago | `x-signature`: HMAC-SHA256 de `data.id` + `x-request-id` + `ts` | que a notificação é da sua aplicação e aponta para aquele recurso |
| Woovi/OpenPix | `x-webhook-signature`: RSA-SHA256 do corpo, conferido com a chave pública da Woovi | que o corpo veio da Woovi |
| AbacatePay | `webhookSecret` na query e, com a chave, `X-Webhook-Signature` | que a URL é a sua e, com a chave, que o corpo não mudou |

No Mercado Pago, informe o `data.id` da query com `setDataId()`: ele entra no
cálculo, e omiti-lo muda o manifesto. Na Woovi, a chave pública é buscada em
`api/v1/webhook/public-keys` na primeira verificação — passe `setPublicKeys()`
para evitar a chamada — e a lista inteira é aceita, porque durante uma rotação
ainda chegam notificações assinadas pela chave anterior.

Quem **não** declara a capacidade: **Efí**, porque a notificação Pix chega por
mTLS e o certificado já cobre isso; **Cielo** e **Rede**, porque a notificação
não traz assinatura e o caminho que a própria documentação indica é consultar a
transação de volta; e **Pagar.me**, porque a v5 não documenta esquema de
assinatura. Nos quatro, `webhookSignature()` levanta `NotImplementedException`
em vez de devolver um verificador que sempre diz sim.
