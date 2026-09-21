<?php

namespace PHPay\PagBank\Interface;

use PHPay\Contracts\{SupportsCharges, SupportsCustomers, SupportsSubscriptions};
use PHPay\PagBank\Resources\Charge\Charge;
use PHPay\PagBank\Resources\Customer\Customer;
use PHPay\PagBank\Resources\Subscription\Subscription;
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
}
