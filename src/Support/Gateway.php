<?php

namespace PHPay\Support;

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\Asaas\AsaasGateway;
use PHPay\Cielo\CieloGateway;
use PHPay\Contracts\GatewayInterface;
use PHPay\Efi\EfiGateway;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\Certificate;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagBank\PagBankGateway;
use PHPay\Rede\RedeGateway;
use PHPay\Woovi\WooviGateway;

/**
 * the gateways the library ships, by name.
 *
 * `PHPay::gateway(new AsaasGateway(...))` stays the way in for whoever chose
 * the gateway while writing the code — it is the path that gives static
 * checking. This enum is for whoever did not: a name from configuration, a
 * gateway picked at runtime, a framework bridge turning an array into an
 * object.
 *
 * the constructors do not agree, which is why resolving by name needs a place
 * to live: six gateways take one credential and three take two, and three have
 * no `$sandbox` at all because their environment comes from the credential's
 * own prefix.
 *
 * **this is the one place in the core that knows the concrete gateways**, and
 * that is what a factory is. The property that matters — the facade dispatches
 * by capability and never names a concrete gateway — is untouched, and the
 * autoloader still only loads the class that gets built.
 */
enum Gateway: string
{
    case ASAAS        = 'asaas';
    case WOOVI        = 'woovi';
    case EFI          = 'efi';
    case MERCADO_PAGO = 'mercadopago';
    case PAGBANK      = 'pagbank';
    case PAGARME      = 'pagarme';
    case CIELO        = 'cielo';
    case REDE         = 'rede';
    case ABACATEPAY   = 'abacatepay';

    /**
     * the concrete class of this gateway.
     *
     * @return class-string<GatewayInterface>
     */
    public function gatewayClass(): string
    {
        return match ($this) {
            self::ASAAS        => AsaasGateway::class,
            self::WOOVI        => WooviGateway::class,
            self::EFI          => EfiGateway::class,
            self::MERCADO_PAGO => MercadoPagoGateway::class,
            self::PAGBANK      => PagBankGateway::class,
            self::PAGARME      => PagarMeGateway::class,
            self::CIELO        => CieloGateway::class,
            self::REDE         => RedeGateway::class,
            self::ABACATEPAY   => AbacatePayGateway::class,
        };
    }

    /**
     * the config keys this gateway requires, in constructor order.
     *
     * @return array<int, string>
     */
    public function credentials(): array
    {
        return match ($this) {
            self::ASAAS, self::PAGBANK, self::ABACATEPAY => ['token'],
            self::WOOVI                                  => ['appId'],
            self::MERCADO_PAGO                           => ['accessToken'],
            self::PAGARME                                => ['secretKey'],
            self::EFI                                    => ['clientId', 'clientSecret'],
            self::CIELO                                  => ['merchantId', 'merchantKey'],
            self::REDE                                   => ['affiliation', 'secret'],
        };
    }

    /**
     * whether the constructor takes a `sandbox` flag.
     *
     * false on Mercado Pago, Pagar.me and AbacatePay, and not by omission: the
     * first two read the environment from the credential's prefix (`TEST-`,
     * `sk_test_`) and AbacatePay has no prefix at all, so there is nothing a
     * flag could switch.
     *
     * @return bool
     */
    public function supportsSandbox(): bool
    {
        return match ($this) {
            self::MERCADO_PAGO, self::PAGARME, self::ABACATEPAY => false,
            default                                             => true,
        };
    }

    /**
     * the optional keys this gateway accepts beyond its credentials.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        $options = $this->supportsSandbox() ? ['sandbox'] : [];

        /* the Pix API of the Efí is mTLS-only, so the certificate is config */
        if ($this === self::EFI) {
            $options[] = 'certificate';
        }

        return $options;
    }

    /**
     * build the gateway from a config array.
     *
     * @param array<string, mixed> $config
     * @return GatewayInterface
     * @throws ValidationException
     */
    public function make(array $config = []): GatewayInterface
    {
        GatewayConfigRequest::validate(
            $this->label(),
            $config,
            $this->credentials(),
            $this->options()
        );

        $sandbox = $this->sandboxOf($config);

        return match ($this) {
            self::ASAAS        => new AsaasGateway($this->credential($config, 'token'), $sandbox),
            self::WOOVI        => new WooviGateway($this->credential($config, 'appId'), $sandbox),
            self::PAGBANK      => new PagBankGateway($this->credential($config, 'token'), $sandbox),
            self::MERCADO_PAGO => new MercadoPagoGateway($this->credential($config, 'accessToken')),
            self::PAGARME      => new PagarMeGateway($this->credential($config, 'secretKey')),
            self::ABACATEPAY   => new AbacatePayGateway($this->credential($config, 'token')),
            self::CIELO        => new CieloGateway(
                $this->credential($config, 'merchantId'),
                $this->credential($config, 'merchantKey'),
                $sandbox
            ),
            self::REDE => new RedeGateway(
                $this->credential($config, 'affiliation'),
                $this->credential($config, 'secret'),
                $sandbox
            ),
            self::EFI => new EfiGateway(
                $this->credential($config, 'clientId'),
                $this->credential($config, 'clientSecret'),
                $sandbox,
                null,
                $this->certificateOf($config)
            ),
        };
    }

    /**
     * the name as it reads in an exception message.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ASAAS        => 'Asaas',
            self::WOOVI        => 'Woovi',
            self::EFI          => 'Efí',
            self::MERCADO_PAGO => 'Mercado Pago',
            self::PAGBANK      => 'PagBank',
            self::PAGARME      => 'Pagar.me',
            self::CIELO        => 'Cielo',
            self::REDE         => 'Rede',
            self::ABACATEPAY   => 'AbacatePay',
        };
    }

    /**
     * the case of a name, with a message that lists the alternatives.
     *
     * `Gateway::tryFrom()` is still there for whoever wants null instead; this
     * exists because a name usually comes from configuration, and a `ValueError`
     * from the enum would not say what the valid names are.
     *
     * @param string $name
     * @return self
     * @throws ValidationException
     */
    public static function named(string $name): self
    {
        return self::tryFrom(strtolower(trim($name)))
            ?? throw GatewayConfigRequest::unknownGateway($name, self::names());
    }

    /**
     * every name the library answers to.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $gateway): string => $gateway->value, self::cases());
    }

    /**
     * a credential read as a string, already validated as present.
     *
     * @param array<string, mixed> $config
     * @param string $key
     * @return string
     */
    private function credential(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        /* the request already refused anything else; this narrows for level 9 */
        return is_string($value) ? $value : '';
    }

    /**
     * the sandbox flag, defaulting to true like every constructor does.
     *
     * @param array<string, mixed> $config
     * @return bool
     */
    private function sandboxOf(array $config): bool
    {
        return is_bool($config['sandbox'] ?? null) ? $config['sandbox'] : true;
    }

    /**
     * the certificate of the Efí, as a path or as the value object.
     *
     * @param array<string, mixed> $config
     * @return Certificate|string|null
     */
    private function certificateOf(array $config): Certificate|string|null
    {
        $certificate = $config['certificate'] ?? null;

        return $certificate instanceof Certificate || is_string($certificate)
            ? $certificate
            : null;
    }
}
