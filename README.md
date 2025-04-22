![capa-redes](https://github.com/user-attachments/assets/20051d3a-ecbf-4d01-8c29-50c43b8d3af4)

<p align="center">
    <a href="https://github.com/phpay-io/phpay/releases"><img src="https://poser.pugx.org/phpay-io/phpay/v/stable" alt="Stable Version"></a>
    <a href="https://www.php.net"><img src="https://img.shields.io/badge/php-%3E=8.1-brightgreen.svg?maxAge=2592000" alt="Php Version"></a>
    <a href="https://packagist.org/packages/phpay-io/phpay"><img src="https://poser.pugx.org/phpay-io/phpay/downloads" alt="Total Downloads"></a>
</p>

O PHPay é uma biblioteca PHP que tem o objetivo tornar o trabalho de integrações com gateways de pagamento mais simples e descomplicadas, facilitando a conexão entre tecnologia e negócios em produtos de software.

## 💸 Gateways

- Asaas (cobranças, gestão de clientes e webhooks)
- Efí (cobranças)

## 📦 Instalação

Instale via Composer:

```php
composer require phpay-io/phpay
```

## ⚙️ Como usar o PHPay?

```php
/**
 * instance with gateway inject
 * @var AsaasGateway $phpay
 */
$phpay = (new PHPay(new AsaasGateway(TOKEN_ASAAS_SANDBOX)));
```

### Cobranças

```php
/**
 * instance with gateway inject and resource call
 *
 * @var Charge $phpay
 */
$phpay = (new PHPay(new AsaasGateway(TOKEN_ASAAS_SANDBOX)))->charge();

/**
 * create charge
 */
$phpay
    ->setCharge($charge)
    ->setCustomer($customer)
    ->create();

/**
 * find charge
 */
$phpay->find($chargeId);

/**
 * get all charges
 */
$phpay->getAll();

/**
 * get all charges with filters
 */
$phpay
    ->setQueryParams(['limit' => 2])
    ->getAll();

/**
 * update charge
 */
$phpay->update($chargeId, $data);

/**
 * destroy charge
 */
$phpay->destroy($chargeId);

/**
 * restore charge
 */
$phpay->restore($chargeId);

/**
 * get status charge
 */
$phpay->getStatus($chargeId);

/**
 * get digitable line charge
 */
$phpay->getDigitableLine($chargeId);

/**
 * get qrcode charge
 */
$phpay->getQrCodePix($chargeId);

/**
 * confirm receipt charge
 */
$phpay->confirmReceipt($chargeId, [
    'paymentDate'    => date('Y-m-d'),
    'value'          => 100.00,
    'notifyCustomer' => true,
]);

/**
 * undo confirm receipt
 */
$phpay->undoConfirmReceipt($chargeId);

```

### Assinaturas

```php
/**
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX))->subscription();

/**
 * create a new subscription
 */
$phpay->setCustomer($customer)->create([
    'billingType' => 'BOLETO',
    'value'       => 100,
    'nextDueDate' => '2025-04-09',
]);
```

## 📝 Roadmap

- Definições de Arquitetura ✅
- Domínios ✅
- Documentação ✍️
- Site 🕛
- Gateways ✍️

  - Asaas.

  - Cobranças ✅
  - Clientes ✅
  - Webhook ✅
  - Assinaturas ✍️
  - Pix 🕥

  - Efí.

  - Autorização ✅
  - Cobranças ✅
  - Clientes 🕥
  - Webhook 🕥
  - Assinaturas ✍️
  - Pix 🕥

- Lançamento v1.0.0 🚀

## 🌟 Contribuindo

Para contribuir com o PHPay, implementando melhorias e novos gateways de pagamento,
leia nosso manual de contribuição. [MANUAL DE CONTRIBUIÇÃO PHPAY](./CONTRIBUTING.md)

## 📄 Licença

Este projeto está licenciado sob a MIT License. Consulte o arquivo [LICENSE](./LICENSE.md) para mais detalhes.

## 🤝 Contato

💻 GitHub: [Mário Lucas](https://github.com/mariolucasdev)

📧 Email: fale@phpay.io

🎉 Comece a usar o PHPay e simplifique suas integrações com gateways de pagamento!
