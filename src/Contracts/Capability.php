<?php

namespace PHPay\Contracts;

/**
 * every resource a gateway may support.
 *
 * each case maps to the interface a gateway implements to declare it, which
 * keeps runtime discovery and static typing describing the same thing.
 */
enum Capability: string
{
    case CUSTOMERS     = 'customers';
    case CHARGES       = 'charges';
    case WEBHOOKS      = 'webhooks';
    case PIX_KEYS      = 'pix-keys';
    case SUBSCRIPTIONS = 'subscriptions';

    /**
     * interface that declares this capability.
     *
     * @return class-string<GatewayInterface>
     */
    public function contract(): string
    {
        return match ($this) {
            self::CUSTOMERS     => SupportsCustomers::class,
            self::CHARGES       => SupportsCharges::class,
            self::WEBHOOKS      => SupportsWebhooks::class,
            self::PIX_KEYS      => SupportsPixKeys::class,
            self::SUBSCRIPTIONS => SupportsSubscriptions::class,
        };
    }

    /**
     * label used in exception messages.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::CUSTOMERS     => 'clientes',
            self::CHARGES       => 'cobranças',
            self::WEBHOOKS      => 'webhooks',
            self::PIX_KEYS      => 'chaves Pix',
            self::SUBSCRIPTIONS => 'assinaturas',
        };
    }

    /**
     * whether the given gateway declares this capability.
     *
     * @param GatewayInterface $gateway
     * @return bool
     */
    public function supportedBy(GatewayInterface $gateway): bool
    {
        return $gateway instanceof ($this->contract());
    }

    /**
     * every capability the given gateway declares.
     *
     * @param GatewayInterface $gateway
     * @return array<int, self>
     */
    public static function of(GatewayInterface $gateway): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $capability) => $capability->supportedBy($gateway)
        ));
    }
}
