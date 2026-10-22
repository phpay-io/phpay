<?php

declare(strict_types=1);

/*
 * Smoke test da versão mínima de PHP que o composer.json promete.
 *
 * O toolchain de dev (Pest 3, Termwind 2) exige PHP >= 8.2, então a suíte não
 * roda na versão mínima suportada. Este script cobre o que dá para cobrir sem
 * ela: carrega o autoload, instancia todos os gateways, percorre as
 * capacidades que cada um declara e exercita os value objects, o modelo de
 * erros, a verificação de assinatura e o caminho de mTLS.
 *
 * Nenhuma requisição HTTP sai daqui. Os métodos terminais (create, find,
 * getAll, ...) não são chamados: o que se prova é que as classes carregam e
 * constroem — inclusive o client que a ClientFactory monta, com o middleware
 * da RetryPolicy.
 *
 * A Efí é a exceção do laço de capacidades: todo acessor dela troca credencial
 * por token antes de devolver o Resource, e isso é rede. Os Resources dela são
 * construídos direto, com um token falso, como a suíte faz.
 *
 * Nenhuma credencial aqui é real — são strings fixas, e o CPF é o mesmo
 * sequencial usado nos testes.
 *
 * Uso: php .github/scripts/smoke.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\Asaas\AsaasGateway;
use PHPay\Cielo\CieloGateway;
use PHPay\Contracts\{Capability, GatewayInterface};
use PHPay\Efi\EfiGateway;
use PHPay\Efi\Resources\Charge\Charge as EfiCharge;
use PHPay\Efi\Resources\Pix\Pix as EfiPix;
use PHPay\Efi\Resources\PixCharge\PixCharge as EfiPixCharge;
use PHPay\Efi\Resources\Subscription\Subscription as EfiSubscription;
use PHPay\Efi\Resources\Webhook\Webhook as EfiWebhook;
use PHPay\Exceptions\{NotImplementedException, ValidationException};
use PHPay\Http\Certificate;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagBank\PagBankGateway;
use PHPay\PHPay;
use PHPay\Rede\RedeGateway;
use PHPay\Support\{Customer, Money};
use PHPay\Woovi\WooviGateway;

/** @var list<string> $failures */
$failures = [];

$checks = 0;

/**
 * roda uma verificação e segue em frente quando ela falha, para que uma saída
 * só mostre tudo o que está quebrado na versão mínima.
 */
$check = function (string $what, callable $probe) use (&$failures, &$checks): void {
    $checks++;

    try {
        $probe();
        printf("  ok    %s\n", $what);
    } catch (\Throwable $e) {
        $failures[] = sprintf('%s — %s: %s', $what, get_class($e), $e->getMessage());
        printf("  FALHA %s\n", $what);
    }
};

$ensure = function (bool $condition, string $message): void {
    if (!$condition) {
        throw new \RuntimeException($message);
    }
};

$expect = function (string $exception, callable $probe) use ($ensure): void {
    try {
        $probe();
    } catch (\Throwable $e) {
        $ensure($e instanceof $exception, sprintf('esperava %s, veio %s: %s', $exception, get_class($e), $e->getMessage()));

        return;
    }

    throw new \RuntimeException(sprintf('esperava %s, nada foi lançado', $exception));
};

printf("PHP %s (%s)\n", PHP_VERSION, PHP_OS_FAMILY);
printf("extensões: curl=%s json=%s openssl=%s\n\n", ...array_map(
    static fn (string $extension): string => extension_loaded($extension) ? 'sim' : 'NÃO',
    ['curl', 'json', 'openssl']
));

/*
 * Um certificado autoassinado, gerado aqui, para o caminho de mTLS da API Pix
 * da Efí. Serve de prova dupla: a ext-openssl funciona nesta versão e a
 * Certificate aceita o .pem pela extensão, sem opção de cURL extra.
 */
$certificate = null;

$check('ext-openssl gera o par de chaves do certificado de teste', function () use (&$certificate, $ensure): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $ensure($key !== false, 'openssl_pkey_new falhou');

    $csr = openssl_csr_new(['commonName' => 'phpay.test'], $key);
    $ensure($csr !== false, 'openssl_csr_new falhou');

    $signed = openssl_csr_sign($csr, null, $key, 1);
    $ensure($signed !== false, 'openssl_csr_sign falhou');

    openssl_x509_export($signed, $pem);
    openssl_pkey_export($key, $privateKey);

    $certificate = Certificate::fromBase64(base64_encode($pem . $privateKey), null, 'pem');

    $ensure($certificate->format() === 'pem', 'formato do certificado deveria ser pem');
    $ensure(array_key_exists('cert', $certificate->guzzleOptions()), 'guzzleOptions deveria trazer cert');
});

/*
 * Todos os gateways, com credenciais falsas. O construtor de nenhum deles faz
 * rede: a Efí só troca credencial por token quando um acessor é chamado, e a
 * Rede só quando o método terminal pede o Bearer.
 */
$gateways = [
    'Asaas'        => new AsaasGateway('fake-token'),
    'Woovi'        => new WooviGateway('fake-app-id'),
    'Efí'          => new EfiGateway('fake-client-id', 'fake-client-secret', true, null, $certificate),
    'Mercado Pago' => new MercadoPagoGateway('TEST-fake-access-token'),
    'PagBank'      => new PagBankGateway('fake-token'),
    'Pagar.me'     => new PagarMeGateway('sk_test_fake'),
    'Cielo'        => new CieloGateway('fake-merchant-id', 'fake-merchant-key'),
    'Rede'         => new RedeGateway('fake-affiliation', 'fake-secret'),
    'AbacatePay'   => new AbacatePayGateway('fake-token'),
];

$accessors = [
    Capability::CUSTOMERS->value         => 'customer',
    Capability::CHARGES->value           => 'charge',
    Capability::WEBHOOKS->value          => 'webhook',
    Capability::PIX_KEYS->value          => 'pix',
    Capability::SUBSCRIPTIONS->value     => 'subscription',
    Capability::WEBHOOK_SIGNATURE->value => 'webhookSignature',
];

// um gateway novo que não entre na lista acima faz este check falhar, em vez
// de passar sem nunca ter sido carregado na versão mínima.
$check('a lista cobre todos os gateways de src/Gateways', function () use ($gateways, $ensure): void {
    $found = glob(__DIR__ . '/../../src/Gateways/*/*Gateway.php') ?: [];

    $ensure(
        count($found) === count($gateways),
        sprintf('src/Gateways tem %d gateways, o smoke test instancia %d', count($found), count($gateways))
    );
});

echo "\ngateways\n";

foreach ($gateways as $label => $gateway) {
    $check(sprintf('%s: facade, nome e capacidades', $label), function () use ($gateway, $ensure): void {
        $phpay = PHPay::gateway($gateway);

        $ensure($gateway instanceof GatewayInterface, 'deveria implementar GatewayInterface');
        $ensure($phpay->name() !== '', 'name() não deveria ser vazio');
        $ensure($phpay->capabilities() !== [], 'capabilities() não deveria ser vazio');
    });

    // a Efí fica fora: os acessores dela fazem rede antes de devolver o
    // Resource. Os Resources dela são construídos logo abaixo.
    if ($label === 'Efí') {
        continue;
    }

    $phpay = PHPay::gateway($gateway);

    foreach (Capability::cases() as $capability) {
        $accessor = $accessors[$capability->value];
        $declared = $phpay->supports($capability);

        $check(
            sprintf('%s: %s() %s', $label, $accessor, $declared ? 'constrói o recurso' : 'recusa a capacidade'),
            function () use ($phpay, $accessor, $declared, $ensure, $expect): void {
                if (!$declared) {
                    $expect(NotImplementedException::class, static fn () => $phpay->{$accessor}());

                    return;
                }

                $ensure(is_object($phpay->{$accessor}()), sprintf('%s() deveria devolver um objeto', $accessor));
            }
        );
    }
}

echo "\nEfí (Resources construídos direto, sem troca de token)\n";

$token = ['access_token' => 'fake-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600];

$check('Efí: Charge da API de Cobranças', function () use ($token, $ensure): void {
    $ensure(new EfiCharge($token) instanceof EfiCharge, 'deveria construir');
});

foreach (['PixCharge' => EfiPixCharge::class, 'Pix' => EfiPix::class, 'Subscription' => EfiSubscription::class] as $name => $class) {
    $check(sprintf('Efí: %s da API Pix (mTLS)', $name), function () use ($class, $token, $certificate, $ensure): void {
        $ensure(new $class($token, $certificate) instanceof $class, 'deveria construir');
    });
}

$check('Efí: Webhook da API Pix (mTLS)', function () use ($token, $certificate, $ensure): void {
    $ensure(new EfiWebhook($token, [], $certificate) instanceof EfiWebhook, 'deveria construir');
});

$check('Efí: API Pix sem certificado explica o mTLS', function () use ($token, $expect): void {
    $expect(ValidationException::class, static fn () => new EfiPix($token));
});

echo "\nvalue objects\n";

$check('Money: reais e centavos convertem nos dois sentidos', function () use ($ensure): void {
    $ensure(Money::reais(10.5)->toCentavos() === 1050, 'reais(10.5) deveria dar 1050 centavos');
    $ensure(Money::reais('100,50')->toCentavos() === 10050, 'reais("100,50") deveria dar 10050 centavos');
    $ensure(Money::centavos(1050)->toReais() === 10.5, 'centavos(1050) deveria dar 10.5 reais');
    $ensure(Money::centavos(123456)->toDecimal() === '1234.56', 'toDecimal() deveria dar "1234.56"');
    $ensure(Money::asCentavos(1050) === 1050, 'asCentavos() deveria aceitar número cru');
    $ensure(Money::asReais(Money::centavos(1050)) === 10.5, 'asReais() deveria aceitar Money');
});

$check('Money: mais de duas casas decimais é recusado', function () use ($expect): void {
    $expect(ValidationException::class, static fn () => Money::reais(10.999));
});

$check('Customer: documento e telefone são derivados', function () use ($ensure): void {
    $customer = Customer::make(
        name: 'Fulano de Teste',
        email: 'fulano@phpay.test',
        document: '123.456.789-01',
        phone: '(11) 99999-9999',
    );

    $ensure($customer->isIndividual(), 'deveria ser pessoa física');
    $ensure($customer->documentType() === 'CPF', 'documentType() deveria ser CPF');
    $ensure($customer->firstName() === 'Fulano', 'firstName() deveria ser Fulano');
    $ensure($customer->phoneParts() !== null, 'phoneParts() não deveria ser nulo');
});

echo "\nassinatura de webhook\n";

$check('PagBank: assinatura válida passa e inválida não', function () use ($gateways, $ensure): void {
    $payload   = '{"id":"CHAR_FAKE"}';
    $secret    = 'fake-webhook-secret';
    $signature = hash('sha256', $secret . '-' . $payload);

    $verifier = $gateways['PagBank']->webhookSignature();

    $ensure(
        $verifier->setSecret($secret)->setPayload($payload)->setHeaders(['x-authenticity-token' => $signature])->verify(),
        'assinatura válida deveria passar'
    );

    $ensure(
        !$gateways['PagBank']->webhookSignature()
            ->setSecret($secret)
            ->setPayload($payload)
            ->setHeaders(['X-AUTHENTICITY-TOKEN' => str_repeat('0', 64)])
            ->verify(),
        'assinatura inválida não deveria passar'
    );
});

$check('assinatura sem corpo cru é erro de uso, não assinatura inválida', function () use ($gateways, $expect): void {
    $expect(ValidationException::class, static fn () => $gateways['PagBank']
        ->webhookSignature()
        ->setSecret('fake-webhook-secret')
        ->setPayload('')
        ->setHeaders([])
        ->verify());
});

printf("\n%d verificações, %d falha(s)\n", $checks, count($failures));

if ($failures !== []) {
    echo "\n";

    foreach ($failures as $failure) {
        printf("  - %s\n", $failure);
    }

    exit(1);
}

printf("o pacote carrega e roda no PHP %s\n", PHP_VERSION);
