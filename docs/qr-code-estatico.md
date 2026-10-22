# QR Code estático

Um QR Code estático de Pix não se pede a ninguém: pelo padrão do BACEN ele é
**composto localmente**, a partir da chave. Quem oferece endpoint para isso — o
Asaas e a Woovi — faz por conveniência, e a Efí não ter um não a torna menos
PSP.

`PHPay\Support\StaticPixCode` compõe o payload, sem tocar a rede:

```php
use PHPay\Support\{Money, StaticPixCode};

$code = StaticPixCode::make(
    key: 'fale@phpay.io',
    merchantName: 'Sixtec LTDA',
    merchantCity: 'BRASILIA',
);

echo $code->payload();
// 00020126350014br.gov.bcb.pix0113fale@phpay.io520400005303986...
```

Sem valor, o pagador digita quanto pagar — é o uso mais comum do estático. Com
valor e com identificador:

```php
$code = StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA')
    ->withAmount(Money::reais('100,50'))
    ->withTxid('PEDIDO1234');
```

O objeto é imutável: cada `with...()` devolve outro, como no `Money`.

## O que saber antes de usar

**O nome do recebedor não é o que o pagador vê.** O app mostra o nome que a
chave resolve no DICT; o campo 59 do payload é exigido pelo padrão e ignorado
pelo app do pagador. Ele é obrigatório e cosmético — não construa nada em cima
dele.

**Só o copia-e-cola.** Renderizar a imagem exige um codificador de QR Code, e o
runtime desta biblioteca é `ext-curl`, `ext-json`, `ext-openssl` e o Guzzle.
Passe o payload para o gerador que você já usa.

**O txid aceita letras e números, até 25.** Sem txid, o código leva `***`, que
é como o padrão diz "o recebedor não definiu um". `PEDIDO-1234` **não** passa:
hífen não é aceito, e um leitor que recusa o código não explica por quê.

**O valor vai em reais com ponto.** `withAmount(100)` é cem reais, porque reais
é a unidade do campo. Para centavos, `Money::centavos(10050)` — ver
[Valores monetários](valores-monetarios.md).

**A chave não é validada no formato.** EVP, e-mail, telefone e CPF/CNPJ têm
formas diferentes, e uma validação apressada reprovaria chave boa. São
recusadas a chave vazia e a que passa de 77 caracteres, que é o que cabe no
campo.

## Por que o CRC importa

O último campo do payload é um CRC-16 — polinômio `0x1021`, valor inicial
`0xFFFF` — e ele **cobre também o literal `6304`** que o anuncia. Parar o
cálculo antes desses quatro caracteres é o erro mais comum de quem implementa o
padrão, e o resultado é um código que simplesmente não lê: o erro aparece na
frente do pagador, não no seu log.

A suíte carrega o vetor de teste do próprio Manual de Padrões para Iniciação do
Pix e exige o payload **byte a byte**, incluindo o `1D3D` final. Para o mesmo
vetor, o cálculo que para antes do `6304` devolve `4AAD`.
