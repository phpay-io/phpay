<?php

declare(strict_types = 1);

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
 * A Efí e o Inter são a exceção do laço de capacidades: todo acessor dos dois
 * troca credencial por token antes de devolver o Resource, e isso é rede. Os
 * Resources deles são construídos direto, com um token falso, como a suíte faz.
 *
 * Nenhuma credencial aqui é real — são strings fixas, e o CPF é o mesmo
 * sequencial usado nos testes.
 *
 * Uso: php .github/scripts/smoke.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\AbacatePay\Enums\BillingStatusEnum;
use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Enums\InvoiceStatusEnum;
use PHPay\Cielo\CieloGateway;
use PHPay\Cielo\Enums\SaleStatusEnum;
use PHPay\Contracts\{Capability, GatewayInterface, SupportsRefunds};
use PHPay\Efi\EfiGateway;
use PHPay\Efi\Enums\{ChargeStatusEnum as EfiChargeStatusEnum, PixChargeStatusEnum};
use PHPay\Efi\Resources\Charge\Charge as EfiCharge;
use PHPay\Efi\Resources\Pix\Pix as EfiPix;
use PHPay\Efi\Resources\PixCharge\PixCharge as EfiPixCharge;
use PHPay\Efi\Resources\Subscription\Subscription as EfiSubscription;
use PHPay\Efi\Resources\Webhook\Webhook as EfiWebhook;
use PHPay\Exceptions\{NotImplementedException, ValidationException};
use PHPay\Http\Certificate;
use PHPay\Inter\Enums\{PixChargeStatusEnum as InterPixChargeStatusEnum, RefundStatusEnum as InterRefundStatusEnum};
use PHPay\Inter\InterGateway;
use PHPay\Inter\Resources\Charge\Charge as InterCharge;
use PHPay\Inter\Resources\Webhook\Webhook as InterWebhook;
use PHPay\MercadoPago\Enums\PaymentStatusEnum;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PagarMe\Enums\ChargeStatusEnum as PagarMeChargeStatusEnum;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagBank\Enums\ChargeStatusEnum as PagBankChargeStatusEnum;
use PHPay\PagBank\PagBankGateway;
use PHPay\PHPay;
use PHPay\Rede\Enums\TransactionStatusEnum;
use PHPay\Rede\RedeGateway;
use PHPay\Support\{Customer, Money, StaticPixCode};
use PHPay\Support\{Gateway, PaymentStatus};
use PHPay\Webhooks\{Event, EventType};
use PHPay\Woovi\Enums\ChargeStatusEnum as WooviChargeStatusEnum;
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
    'Inter'        => new InterGateway('fake-client-id', 'fake-client-secret', true, $certificate),
];

// o estorno e a leitura de webhook ficam fora: são as duas capacidades que
// executam uma operação em vez de devolver um recurso, então não têm acessor
// para construir. As duas são verificadas em seguida, à parte.
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

    $ensure(
        count($found) === count(Gateway::cases()),
        sprintf('src/Gateways tem %d gateways, o enum Gateway lista %d', count($found), count(Gateway::cases()))
    );
});

// o registry é o caminho de quem não sabe o gateway em tempo de escrita, e
// montar os dez por nome prova que todo case tem braço no match do make().
$check('o registry constrói os dez por nome', function () use ($ensure, $expect): void {
    foreach (Gateway::cases() as $gateway) {
        $config = array_fill_keys($gateway->credentials(), 'credencial-falsa');

        $construido = PHPay::make($gateway, $config);

        $ensure($construido->name() !== '', sprintf('%s: name() não deveria ser vazio', $gateway->value));
        $ensure($construido->capabilities() !== [], sprintf('%s: sem capacidades', $gateway->value));
    }

    $expect(ValidationException::class, static fn () => PHPay::make('xpto', []));
    $expect(ValidationException::class, static fn () => Gateway::ASAAS->make([]));
    $expect(ValidationException::class, static fn () => Gateway::ASAAS->make(['token' => 'x', 'typo' => 1]));

    // os três sem sandbox recusam a chave em vez de ignorá-la
    $expect(ValidationException::class, static fn () => Gateway::MERCADO_PAGO->make([
        'accessToken' => 'TEST-x',
        'sandbox'     => true,
    ]));
});

echo "\ngateways\n";

foreach ($gateways as $label => $gateway) {
    $check(sprintf('%s: facade, nome e capacidades', $label), function () use ($gateway, $ensure): void {
        $phpay = PHPay::gateway($gateway);

        $ensure($gateway instanceof GatewayInterface, 'deveria implementar GatewayInterface');
        $ensure($phpay->name() !== '', 'name() não deveria ser vazio');
        $ensure($phpay->capabilities() !== [], 'capabilities() não deveria ser vazio');
    });

    // a Efí e o Inter ficam fora: os acessores dos dois fazem rede antes de
    // devolver o Resource. Os Resources deles são construídos logo abaixo.
    if ($label === 'Efí' || $label === 'Inter') {
        continue;
    }

    $phpay = PHPay::gateway($gateway);

    foreach (Capability::cases() as $capability) {
        $declared = $phpay->supports($capability);

        // leitura de webhook é parsing puro, então dá para exercitar nos dois
        // sentidos sem rede: nos que declaram, um corpo mínimo tem que virar
        // lista de Event; nos que não, a facade recusa.
        if ($capability === Capability::WEBHOOK_EVENTS) {
            $check(
                sprintf('%s: webhookEvents() %s', $label, $declared ? 'lê a notificação' : 'recusa a capacidade'),
                function () use ($phpay, $declared, $ensure, $expect): void {
                    if (!$declared) {
                        $expect(NotImplementedException::class, static fn () => $phpay->webhookEvents(['x' => 1]));

                        return;
                    }

                    $eventos = $phpay->webhookEvents(['x' => 1]);

                    $ensure($eventos !== [], 'deveria devolver ao menos um evento');
                    $ensure($eventos[0] instanceof Event, 'deveria devolver Event');
                    $ensure(
                        $eventos[0]->type === EventType::UNKNOWN || $eventos[0]->type === EventType::UPDATED,
                        'corpo irreconhecível deveria degradar, não levantar'
                    );
                }
            );

            continue;
        }

        // declarado, só o contrato: a chamada iria à rede. Não declarado, a
        // facade recusa antes de qualquer HTTP, e isso dá para exercitar.
        if ($capability === Capability::REFUNDS) {
            $check(
                sprintf('%s: refund() %s', $label, $declared ? 'declara a capacidade' : 'recusa a capacidade'),
                function () use ($phpay, $gateway, $declared, $ensure, $expect): void {
                    if (!$declared) {
                        $expect(NotImplementedException::class, static fn () => $phpay->refund('fake-id'));

                        return;
                    }

                    $ensure($gateway instanceof SupportsRefunds, 'deveria implementar SupportsRefunds');
                }
            );

            continue;
        }

        $accessor = $accessors[$capability->value];

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

echo "\nInter (Resources construídos direto, sem troca de token)\n";

$check('Inter: Charge da API Pix (mTLS)', function () use ($token, $certificate, $ensure): void {
    $ensure(new InterCharge($token, $certificate) instanceof InterCharge, 'deveria construir');
});

$check('Inter: Webhook da API Pix (mTLS)', function () use ($token, $certificate, $ensure): void {
    $ensure(new InterWebhook($token, [], $certificate) instanceof InterWebhook, 'deveria construir');
});

$check('Inter: sem certificado explica o mTLS e o par de arquivos', function () use ($token, $expect): void {
    $expect(ValidationException::class, static fn () => new InterCharge($token));
});

// a notificação do Inter é um array na raiz, não o {"pix": [...]} do padrão.
// Vale no smoke porque é parsing puro, e porque é o desvio que devolveria zero
// eventos em silêncio se alguém "arrumasse" o parser para o envelope do BACEN.
$check('Inter: a notificação é um array na raiz', function () use ($gateways, $ensure): void {
    $eventos = PHPay::gateway($gateways['Inter'])->webhookEvents([[
        'endToEndId' => 'E87654321202009091221dfghi123456',
        'txid'       => '971122d8f37211eaadc10242ac120002',
        'valor'      => '110.00',
        'horario'    => '2020-09-09T20:15:00.358Z',
    ]]);

    $ensure(count($eventos) === 1, sprintf('deveria ler 1 evento, leu %d', count($eventos)));
    $ensure($eventos[0]->type === EventType::PAID, 'deveria ser pagamento recebido');
    $ensure($eventos[0]->status === PaymentStatus::PAID, 'o corpo traz o pagamento, então não precisa consultar');
    $ensure($eventos[0]->resourceId === '971122d8f37211eaadc10242ac120002', 'o id deveria ser o txid');
});

$check('Inter: devolucoes vem como objeto, não como array', function () use ($gateways, $ensure): void {
    $eventos = PHPay::gateway($gateways['Inter'])->webhookEvents([[
        'endToEndId'  => 'E105735212026021113145BVE1RX4kbc6',
        'txid'        => 'c3e0e7a4e7f1469a9f782d3d4999343c',
        'valor'       => '110.00',
        'devolucoes'  => ['id' => '123ABC', 'valor' => '10.00', 'status' => 'DEVOLVIDO'],
    ]]);

    $ensure(count($eventos) === 2, sprintf('pagamento + devolução = 2 eventos, leu %d', count($eventos)));
    $ensure($eventos[1]->type === EventType::REFUNDED, 'o segundo deveria ser estorno');
    $ensure($eventos[1]->status === PaymentStatus::REFUNDED, 'DEVOLVIDO significa que o dinheiro voltou');
});

echo "\nvalue objects\n";

// o ponto do VO: dez vocabulários lidos de uma forma só. Vale no smoke porque é
// mapeamento puro, sem rede — e porque um case novo sem entrada no match aqui
// estoura na versão mínima antes de chegar em quem usa.
$check('PaymentStatus: os vocabulários dos gateways convergem', function () use ($ensure): void {
    $pagas = [
        'Asaas'        => InvoiceStatusEnum::of('RECEIVED'),
        'Woovi'        => WooviChargeStatusEnum::of('COMPLETED'),
        'Cielo'        => SaleStatusEnum::of(2),
        'Rede'         => TransactionStatusEnum::of('00'),
        'AbacatePay'   => BillingStatusEnum::of('PAID'),
        'Mercado Pago' => PaymentStatusEnum::of('approved'),
        'PagBank'      => PagBankChargeStatusEnum::of('PAID'),
        'Pagar.me'     => PagarMeChargeStatusEnum::of('paid'),
        'Efí'          => EfiChargeStatusEnum::of('paid'),
        'Efí (pix)'    => PixChargeStatusEnum::of('CONCLUIDA'),
        'Inter'        => InterPixChargeStatusEnum::of('CONCLUIDA'),
    ];

    foreach ($pagas as $gateway => $status) {
        $ensure($status === PaymentStatus::PAID, sprintf('%s deveria ler como paga', $gateway));
    }

    $ensure(
        InvoiceStatusEnum::of('STATUS_INVENTADO') === PaymentStatus::UNKNOWN,
        'status desconhecido deveria degradar, não levantar'
    );
    $ensure(
        TransactionStatusEnum::of('51') === PaymentStatus::FAILED,
        'na Rede, código não listado é recusa'
    );
    $ensure(!PaymentStatus::PAID->isFinal(), 'paga não é final: estorno ainda vem depois');
    $ensure(
        InterRefundStatusEnum::of('EM_PROCESSAMENTO') === PaymentStatus::PAID,
        'no Inter, devolução em processamento não devolveu nada ainda'
    );
});

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

$check('StaticPixCode: reproduz o vetor de teste do manual do BACEN', function () use ($ensure): void {
    /*
     * byte a byte, porque é o único jeito de provar o CRC: ele cobre o
     * literal `6304` que o anuncia, e parar antes dele dá 4AAD em vez de
     * 1D3D — um QR Code que simplesmente não lê.
     */
    $esperado = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-'
        . '4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

    $payload = StaticPixCode::make('123e4567-e12b-12d1-a456-426655440000', 'Fulano de Tal', 'BRASILIA')
        ->payload();

    $ensure($payload === $esperado, sprintf('payload divergente do manual: %s', $payload));
});

$check('StaticPixCode: valor em reais com ponto e txid no campo 62', function () use ($ensure): void {
    $payload = StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA')
        ->withAmount(Money::reais('100,50'))
        ->withTxid('PEDIDO1234')
        ->payload();

    $ensure(str_contains($payload, '5406100.50'), 'campo 54 deveria ser 06 e 100.50');
    $ensure(str_contains($payload, '62140510PEDIDO1234'), 'campo 62 deveria levar o txid em 05');
    $ensure(!str_contains($payload, '0503***'), 'com txid, não deveria sobrar o *** do padrão');
});

$check('StaticPixCode: chave vazia e txid fora do padrão são recusados', function () use ($expect): void {
    $expect(ValidationException::class, static fn () => StaticPixCode::make('', 'Sixtec LTDA', 'BRASILIA'));
    $expect(
        ValidationException::class,
        static fn () => StaticPixCode::make('fale@phpay.io', 'Sixtec LTDA', 'BRASILIA')->withTxid('PEDIDO-1234')
    );
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
