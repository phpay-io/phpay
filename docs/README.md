# Documentação do PHPay

O que vale para todos os gateways está nos conceitos: capacidade, erro,
unidade monetária, status, retry. Cada página de gateway cobre só o que é
específico dele.

[← voltar ao README](../README.md)

## Conceitos

| | |
| --- | --- |
| [Capacidades](capacidades.md) | o que cada gateway declara suportar, e o que acontece quando você pede o que ele não tem |
| [Resolvendo o gateway](resolvendo-o-gateway.md) | `PHPay::gateway()` quando você sabe qual, `PHPay::make()` quando ele vem de configuração |
| [Tratamento de erros](erros.md) | array é sucesso, falha é exceção — e qual exceção |
| [Retry e idempotência](retry-e-idempotencia.md) | o que a biblioteca repete, o que ela recusa a repetir, e por quê |
| [Testando](testando.md) | `ClientFactory::fake()`, para quem usa a biblioteca testar sem sair para a rede |
| [Ambientes e credenciais](ambientes-e-credenciais.md) | sandbox e produção em cada gateway, e como a credencial fica fora de dump |
| [Cliente](cliente.md) | o VO `Customer`, porque o mesmo campo tem seis grafias entre os gateways |
| [Valores monetários](valores-monetarios.md) | `Money`, porque reais e centavos não se misturam |
| [Status de pagamento](status-de-pagamento.md) | `PaymentStatus`, porque "paga" tem dez grafias entre os gateways |
| [Verificando o webhook que chega](webhooks.md) | provar que a notificação veio do gateway |
| [Certificado e mTLS](mtls.md) | um `.p12` ou um par `.crt` + `.key`, que é como os PSPs de Pix entregam |
| [QR Code estático](qr-code-estatico.md) | compor o BR Code de uma chave Pix localmente, que é como o padrão manda |

## Gateways

| | | |
| --- | --- | --- |
| [Asaas](gateways/asaas.md) | [Woovi/OpenPix](gateways/woovi.md) | [Efí](gateways/efi.md) |
| [Mercado Pago](gateways/mercado-pago.md) | [PagBank](gateways/pagbank.md) | [Pagar.me](gateways/pagarme.md) |
| [AbacatePay](gateways/abacatepay.md) | [Cielo](gateways/cielo.md) | [Rede](gateways/rede.md) |
| [Inter](gateways/inter.md) | | |
