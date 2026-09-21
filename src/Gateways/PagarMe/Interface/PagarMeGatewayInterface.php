<?php

namespace PHPay\PagarMe\Interface;

use PHPay\Contracts\{SupportsCharges, SupportsCustomers, SupportsSubscriptions};
use PHPay\PagarMe\Resources\Charge\Charge;
use PHPay\PagarMe\Resources\Customer\Customer;
use PHPay\PagarMe\Resources\Subscription\Subscription;
use PHPay\PagarMe\Resources\WebhookDelivery\WebhookDelivery;

/**
 * the Pagar.me gateway offers customers, charges and subscriptions.
 *
 * it deliberately does not declare SupportsWebhooks nor SupportsPixKeys:
 *
 * - /hooks reads the webhook events Pagar.me already delivered; registering
 *   the endpoints that receive them is done in the dashboard. That read API
 *   is exposed as webhookDeliveries(), outside the capability model.
 * - Pix is a payment method of an order, not a resource with keys.
 */
interface PagarMeGatewayInterface extends
    SupportsCustomers,
    SupportsCharges,
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
     * get resource subscription from gateway.
     *
     * @return Subscription
     */
    public function subscription(): Subscription;

    /**
     * read the webhook events already delivered by the gateway.
     *
     * gateway specific: not part of any capability, so it is reachable only
     * from the concrete gateway, never through the PHPay facade.
     *
     * @return WebhookDelivery
     */
    public function webhookDeliveries(): WebhookDelivery;

    /**
     * whether the credential in use is a test credential.
     *
     * @return bool
     */
    public function isSandbox(): bool;
}
