<?php

namespace PHPay\Woovi\Interface;

use PHPay\Contracts\{SupportsCharges, SupportsCustomers, SupportsPixKeys, SupportsSubscriptions, SupportsWebhooks};
use PHPay\Woovi\Resources\Charge\Charge;
use PHPay\Woovi\Resources\Customer\Customer;
use PHPay\Woovi\Resources\Pix\Pix;
use PHPay\Woovi\Resources\Subscription\Subscription;
use PHPay\Woovi\Resources\Webhook\Webhook;

/**
 * the Woovi/OpenPix gateway offers every capability the library models.
 *
 * it is the second to do so, after Asaas — and the two are independent
 * companies with independent APIs, which is what tells us the capability
 * model describes the domain rather than one vendor.
 */
interface WooviGatewayInterface extends
    SupportsCustomers,
    SupportsCharges,
    SupportsWebhooks,
    SupportsPixKeys,
    SupportsSubscriptions
{
    /**
     * get resource customer from gateway.
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(array $customer = []): Customer;

    /**
     * get resource charge from gateway.
     *
     * @return Charge
     */
    public function charge(): Charge;

    /**
     * get resource webhook from gateway.
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook;

    /**
     * get resource pix from gateway.
     *
     * @return Pix
     */
    public function pix(): Pix;

    /**
     * get resource subscription from gateway.
     *
     * @return Subscription
     */
    public function subscription(): Subscription;
}
