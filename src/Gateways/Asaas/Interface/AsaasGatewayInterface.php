<?php

namespace PHPay\Asaas\Interface;

use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Pix\Pix;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Asaas\Resources\Webhook\Webhook;
use PHPay\Contracts\{SupportsCharges, SupportsCustomers, SupportsPixKeys, SupportsSubscriptions, SupportsWebhooks};
use PHPay\Support\Customer as CustomerData;

/**
 * the Asaas gateway offers every capability the library models.
 */
interface AsaasGatewayInterface extends
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
    public function customer(CustomerData|array $customer = []): Customer;

    /**
     * get resource charge from gateway.
     *
     * @return Charge
     */
    public function charge(): Charge;

    /**
     * get resource subscription from gateway.
     *
     * @return Subscription
     */
    public function subscription(): Subscription;

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
}
