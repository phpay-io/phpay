# Certificado e mTLS

O padrão do BACEN para API Pix exige **mTLS em toda requisição**, a do token
incluída: o PSP só conversa com quem apresentar o certificado que ele emitiu.
Não é autenticação a mais — sem o certificado a conexão não se estabelece, e a
credencial nem chega a ser lida.

Quem trata disso é `PHPay\Http\Certificate`, em `Http` e não dentro de um
gateway, porque Efí, Inter, BB, Itaú, Sicoob e Sicredi seguem o mesmo esquema.

[← voltar ao índice](README.md)

## Os PSPs entregam de duas formas

E as duas são aceitas, porque é o cURL que lê o arquivo e ele aceita as duas.

| O PSP entrega | Você usa |
| --- | --- |
| **um arquivo** com certificado e chave dentro (`.p12`, `.pem`) | `new Certificate($caminho, $senha)` |
| **dois arquivos**, certificado e chave separados (`.crt` + `.key`) | `Certificate::pair($crt, $key, $senha)` |

A Efí entrega um `.p12`. O Inter entrega `.crt` e `.key`, e a documentação dele
manda juntar os dois com `openssl pkcs12 -export` — o que funciona, mas não é
necessário: o `pair()` entrega as duas metades ao cURL como elas vieram.

```php
use PHPay\Http\Certificate;

/* arquivo único */
new Certificate('/caminho/certificado.p12');
new Certificate('/caminho/certificado.p12', 'senha-da-chave');

/* certificado e chave separados */
Certificate::pair('/caminho/certificado.crt', '/caminho/chave.key');
Certificate::pair('/caminho/certificado.crt', '/caminho/chave.key', 'senha-da-chave');
```

Hoje o único gateway da biblioteca que fala mTLS é a [Efí](gateways/efi.md),
que usa arquivo único. O `pair()` existe porque o `Certificate` é genérico, e é
o formato que o gateway do Inter vai precisar
([#156](https://github.com/phpay-io/phpay/issues/156)).

## A senha é da chave, não do certificado

Num par, a senha protege a **chave privada**. O certificado é público — não tem
o que decifrar. É por isso que `pair()` tem um parâmetro de senha só, e ele vai
para a chave:

```php
Certificate::pair($crt, $key, 'segredo')->guzzleOptions();
// ['cert' => '…/certificado.crt', 'ssl_key' => ['…/chave.key', 'segredo']]
```

> Esse é o detalhe que falha em silêncio se você montar as opções na mão: o
> Guzzle transforma o segundo elemento de `cert` numa senha de certificado
> (`CURLOPT_SSLCERTPASSWD`), e o handshake quebra sem dizer por quê.

## Em container, o certificado vem em variável de ambiente

Para os dois formatos, em base64:

```php
Certificate::fromBase64(getenv('EFI_CERT_BASE64'), getenv('EFI_CERT_SENHA'));

Certificate::pairFromBase64(getenv('CERT_BASE64'), getenv('CHAVE_BASE64'));
```

Os dois gravam em arquivo temporário com permissão `0600`, apagado quando o
processo termina.

O `fromBase64()` aceita um terceiro parâmetro de formato (`'p12'` ou `'pem'`),
porque num arquivo único é a extensão que diz ao cURL se ele é um bundle
PKCS#12 ou um PEM. O `pairFromBase64()` não aceita: as duas metades de um par
são PEM, e a extensão não informa nada.

## Extensões aceitas

| | |
| --- | --- |
| arquivo único | `.p12`, `.pem` |
| certificado de um par | `.crt`, `.cer`, `.pem` |
| chave de um par | `.key`, `.pem` |

A extensão não é capricho: é por ela que o Guzzle diz ao cURL que o arquivo é
PKCS#12. Um `.pfx` é o mesmo formato de um `.p12`, mas o Guzzle não reconhece
essa extensão — a mensagem de erro manda renomear, e renomear resolve.

Um `.crt` sozinho é recusado, e a mensagem aponta o `pair()`: certificado sem
chave privada não fecha handshake nenhum.

## O que o Certificate não faz

- **Não lê nem valida o arquivo.** Quem faz isso é o cURL, na hora da
  requisição. O `Certificate` confere que o arquivo existe, que dá para ler e
  que a extensão é uma que o cURL entende — senha errada ou arquivo corrompido
  aparecem como erro de conexão na primeira chamada.
- **Não guarda a senha onde se possa ler.** `__debugInfo()` troca por
  `********`, então `var_dump()` e `print_r()` não a mostram. Não existe getter
  para ela.
