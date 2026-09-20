<?php

namespace PHPay;

use PHPay\Contracts\{Capability, GatewayInterface, SupportsCharges, SupportsCustomers, SupportsPixKeys, SupportsSubscriptions, SupportsWebhooks};
use PHPay\Exceptions\NotImplementedException;

/**
 * entry point of the library.
 *
 * it accepts any gateway and dispatches to it. because the gateway is only
 * known at runtime, each resource is guarded by the capability the gateway
 * declares — ask with supports() to branch, or let the call throw a message
 * naming what the gateway does offer.
 *
 * holding the concrete gateway instead gives the same guarantee at analysis
 * time: calling pix() on a gateway that does not implement SupportsPixKeys is
 * a static error, not a runtime one.
 */
class PHPay implements GatewayInterface
{
    /**
     * instance of PHPay.
     *
     * @param GatewayInterface $gateway
     */
    public function __construct(
        protected GatewayInterface $gateway
    ) {
    }

    /**
     * instance of PHPay.
     *
     * @param GatewayInterface $gateway
     * @return PHPay
     */
    public static function gateway(GatewayInterface $gateway): PHPay
    {
        return new PHPay($gateway);
    }

    /**
     * name of the wrapped gateway.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->gateway->name();
    }

    /**
     * whether the wrapped gateway offers the given capability.
     *
     * @param Capability $capability
     * @return bool
     */
    public function supports(Capability $capability): bool
    {
        return $capability->supportedBy($this->gateway);
    }

    /**
     * every capability the wrapped gateway offers.
     *
     * @return array<int, Capability>
     */
    public function capabilities(): array
    {
        return Capability::of($this->gateway);
    }

    /**
     * get resource customer from gateway.
     *
     * @param array<mixed> $customer
     * @return object
     * @throws NotImplementedException
     */
    public function customer(array $customer = []): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsCustomers) {
            throw NotImplementedException::forCapability($gateway, Capability::CUSTOMERS);
        }

        return $gateway->customer($customer);
    }

    /**
     * get resource charge from gateway.
     *
     * @return object
     * @throws NotImplementedException
     */
    public function charge(): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsCharges) {
            throw NotImplementedException::forCapability($gateway, Capability::CHARGES);
        }

        return $gateway->charge();
    }

    /**
     * get resource webhook from gateway.
     *
     * @param array<mixed> $webhook
     * @return object
     * @throws NotImplementedException
     */
    public function webhook(array $webhook = []): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsWebhooks) {
            throw NotImplementedException::forCapability($gateway, Capability::WEBHOOKS);
        }

        return $gateway->webhook($webhook);
    }

    /**
     * get resource pix from gateway.
     *
     * @return object
     * @throws NotImplementedException
     */
    public function pix(): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsPixKeys) {
            throw NotImplementedException::forCapability($gateway, Capability::PIX_KEYS);
        }

        return $gateway->pix();
    }

    /**
     * get resource subscription from gateway.
     *
     * @return object
     * @throws NotImplementedException
     */
    public function subscription(): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsSubscriptions) {
            throw NotImplementedException::forCapability($gateway, Capability::SUBSCRIPTIONS);
        }

        return $gateway->subscription();
    }
}
