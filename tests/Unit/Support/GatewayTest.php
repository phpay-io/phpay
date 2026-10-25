<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Resources\Charge\Charge as AsaasCharge;
use PHPay\Cielo\CieloGateway;
use PHPay\Contracts\GatewayInterface;
use PHPay\Efi\EfiGateway;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\Certificate;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PHPay;
use PHPay\Support\Gateway;

/**
 * config mínima daquele gateway, montada a partir do que ele declara exigir.
 *
 * @param Gateway $gateway
 * @return array<string, string>
 */
function configMinima(Gateway $gateway): array
{
    return array_fill_keys($gateway->credentials(), 'credencial-falsa');
}

it('constrói os dez a partir do nome', function (Gateway $gateway) {
    /*
     * o teste varre Gateway::cases(), então gateway novo no enum sem braço no
     * match do make() reprova aqui, e não em produção.
     */
    $construido = $gateway->make(configMinima($gateway));

    expect($construido)->toBeInstanceOf(GatewayInterface::class)
        ->and($construido)->toBeInstanceOf($gateway->gatewayClass())
        ->and($construido->name())->not->toBeEmpty();
})->with(Gateway::cases())->group('support');

it('devolve a facade pelo nome, com o gateway dentro', function () {
    $phpay = PHPay::make('asaas', ['token' => 'fake', 'sandbox' => true]);

    expect($phpay)->toBeInstanceOf(PHPay::class)
        ->and($phpay->name())->toBe('Asaas')
        ->and($phpay->charge())->toBeInstanceOf(AsaasCharge::class);
})->group('support');

it('aceita o nome como string ou como case do enum', function () {
    $porString = PHPay::make('cielo', ['merchantId' => 'm', 'merchantKey' => 'k']);
    $porCase   = PHPay::make(Gateway::CIELO, ['merchantId' => 'm', 'merchantKey' => 'k']);

    expect($porString->name())->toBe($porCase->name());
})->group('support');

it('tolera caixa e espaço no nome, que costuma vir de configuração', function () {
    expect(Gateway::named('ASAAS'))->toBe(Gateway::ASAAS)
        ->and(Gateway::named('  mercadopago  '))->toBe(Gateway::MERCADO_PAGO);
})->group('support');

it('diz quais nomes existem quando o nome está errado', function () {
    /* o nome costuma vir de .env; um ValueError de enum não diria os válidos */
    expect(fn () => PHPay::make('xpto', []))
        ->toThrow(ValidationException::class, 'Não existe gateway "xpto"');

    expect(fn () => Gateway::named('stripe'))
        ->toThrow(ValidationException::class, 'asaas, woovi, efi, mercadopago, pagbank, pagarme, cielo, rede, abacatepay');

    /* quem preferir null continua com o tryFrom do enum */
    expect(Gateway::tryFrom('xpto'))->toBeNull();
})->group('support');

it('exige as credenciais daquele gateway, nomeando a que falta', function () {
    expect(fn () => Gateway::ASAAS->make([]))
        ->toThrow(ValidationException::class, 'Informe a chave "token"');

    /* a Cielo pede duas: a segunda também é exigida */
    expect(fn () => Gateway::CIELO->make(['merchantId' => 'm']))
        ->toThrow(ValidationException::class, 'Informe a chave "merchantKey"');

    expect(fn () => Gateway::EFI->make(['clientId' => 'id']))
        ->toThrow(ValidationException::class, 'clientSecret');
})->group('support');

it('recusa credencial vazia, que não é credencial', function () {
    expect(fn () => Gateway::ASAAS->make(['token' => '']))
        ->toThrow(ValidationException::class, 'token')
        ->and(fn () => Gateway::ASAAS->make(['token' => '   ']))
        ->toThrow(ValidationException::class, 'token');
})->group('support');

it('recusa sandbox onde o ambiente vem do prefixo da credencial', function (Gateway $gateway, string $credencial) {
    /*
     * mandar sandbox para esses três revela que quem escreveu acha que aquilo
     * troca o ambiente — e não troca. Ignorar em silêncio esconderia o
     * mal-entendido até a produção.
     */
    expect($gateway->supportsSandbox())->toBeFalse()
        ->and(fn () => $gateway->make([$credencial => 'x', 'sandbox' => true]))
        ->toThrow(ValidationException::class, 'o ambiente dele vem do prefixo da credencial');
})->with([
    'mercado pago' => [Gateway::MERCADO_PAGO, 'accessToken'],
    'pagar.me'     => [Gateway::PAGARME, 'secretKey'],
    'abacatepay'   => [Gateway::ABACATEPAY, 'token'],
])->group('support');

it('recusa chave que o gateway não aceita, em vez de ignorar', function () {
    /* typo em chave de config é bug que só aparece em produção se for ignorado */
    expect(fn () => Gateway::ASAAS->make(['token' => 'x', 'skandbox' => true]))
        ->toThrow(ValidationException::class, 'A chave "skandbox" não é aceita');

    expect(fn () => Gateway::ASAAS->make(['token' => 'x', 'certificate' => '/caminho.p12']))
        ->toThrow(ValidationException::class, 'certificate');
})->group('support');

it('usa sandbox por padrão, como todo construtor da lib', function () {
    $lerSandbox = function (GatewayInterface $gateway): bool {
        $property = new ReflectionProperty($gateway, 'sandbox');

        return (bool) $property->getValue($gateway);
    };

    expect($lerSandbox(Gateway::ASAAS->make(['token' => 'x'])))->toBeTrue()
        ->and($lerSandbox(Gateway::ASAAS->make(['token' => 'x', 'sandbox' => true])))->toBeTrue()
        ->and($lerSandbox(Gateway::ASAAS->make(['token' => 'x', 'sandbox' => false])))->toBeFalse();
})->group('support');

it('aceita o certificado da efí como caminho ou como value object', function () {
    $config = ['clientId' => 'id', 'clientSecret' => 'secret'];
    $path   = sys_get_temp_dir() . '/phpay-registry-' . bin2hex(random_bytes(6)) . '.p12';

    file_put_contents($path, 'certificado falso');

    try {
        expect(Gateway::EFI->make($config + ['certificate' => $path]))
            ->toBeInstanceOf(EfiGateway::class)
            ->and(Gateway::EFI->make($config + ['certificate' => new Certificate($path)]))
            ->toBeInstanceOf(EfiGateway::class);
    } finally {
        unlink($path);
    }

    expect(Gateway::EFI->options())->toContain('certificate');
})->group('support');

it('deixa o certificado inexistente ser recusado na construção', function () {
    /*
     * o construtor da Efí converte o caminho em Certificate na hora, e ele
     * exige arquivo legível. O registry repassa e não engole: config com
     * caminho errado falha aqui, não na primeira chamada à API Pix.
     */
    expect(fn () => Gateway::EFI->make([
        'clientId'     => 'id',
        'clientSecret' => 'secret',
        'certificate'  => '/nao/existe/certificado.p12',
    ]))->toThrow(ValidationException::class, 'Certificado');
})->group('support');

it('declara o que cada gateway exige e aceita', function () {
    expect(Gateway::ASAAS->credentials())->toBe(['token'])
        ->and(Gateway::ASAAS->options())->toBe(['sandbox'])
        ->and(Gateway::EFI->credentials())->toBe(['clientId', 'clientSecret'])
        ->and(Gateway::MERCADO_PAGO->options())->toBe([])
        ->and(Gateway::CIELO->credentials())->toBe(['merchantId', 'merchantKey'])
        ->and(Gateway::REDE->credentials())->toBe(['affiliation', 'secret']);

    expect(Gateway::names())->toHaveCount(10)
        ->and(Gateway::ASAAS->gatewayClass())->toBe(AsaasGateway::class)
        ->and(Gateway::MERCADO_PAGO->gatewayClass())->toBe(MercadoPagoGateway::class)
        ->and(Gateway::CIELO->gatewayClass())->toBe(CieloGateway::class);

    /*
     * o Inter é o único com quatro opcionais: além de sandbox e certificate,
     * o token é emitido por `scopes` e o `account` preenche x-conta-corrente.
     */
    expect(Gateway::INTER->credentials())->toBe(['clientId', 'clientSecret'])
        ->and(Gateway::INTER->options())->toBe(['sandbox', 'certificate', 'scopes', 'account']);
})->group('support');

it('todo gateway de src/Gateways está no enum', function () {
    /* gateway novo que não entre aqui deixa de ser alcançável por nome */
    $noDisco = glob(__DIR__ . '/../../../src/Gateways/*/*Gateway.php') ?: [];

    expect(Gateway::cases())->toHaveCount(count($noDisco));
})->group('support');

it('exige certificado só quando o recurso monta o próprio client', function () {
    /*
     * construir a Efí sem certificado continua válido: ele só é exigido quando
     * a API Pix vai montar o client dela, como o CLAUDE.md descreve.
     */
    $efi = Gateway::EFI->make(['clientId' => 'id', 'clientSecret' => 'secret']);

    expect($efi)->toBeInstanceOf(EfiGateway::class)
        ->and($efi->name())->toBe('Efí');
})->group('support');

it('não exige certificado como value object para construir', function () {
    expect(class_exists(Certificate::class))->toBeTrue()
        ->and(Gateway::EFI->options())->toBe(['sandbox', 'certificate']);
})->group('support');
