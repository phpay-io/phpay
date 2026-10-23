<?php

namespace PHPay\PagBank\Interface;

use PHPay\Contracts\{SupportsCharges, SupportsCustomers, SupportsRefunds, SupportsSubscriptions, SupportsWebhookEvents, SupportsWebhookSignature};
use PHPay\PagBank\Resources\Charge\Charge;
use PHPay\PagBank\Resources\Customer\Customer;
use PHPay\PagBank\Resources\Subscription\Subscription;
use PHPay\PagBank\Resources\WebhookSignature\WebhookSignature;
use PHPay\Support\Customer as CustomerData;

/**
 * the PagBank gateway offers charges, subscribers and subscriptions.
 *
 * it deliberately does not declare SupportsWebhooks nor SupportsPixKeys:
 *
 * - webhooks have no CRUD API. they are registered in the panel, or per order
 *   through the notification_urls field.
 * - Pix is requested as a qr_codes entry of an order, not a resource with
 *   keys and static QR Codes.
 */
interface PagBankGatewayInterface extends
    SupportsCustomers,
    SupportsCharges,
    SupportsRefunds,
    SupportsSubscriptions,
    SupportsWebhookSignature,
    SupportsWebhookEvents
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
     * get resource webhook signature from gateway.
     *
     * @return WebhookSignature
     */
    public function webhookSignature(): WebhookSignature;
}
