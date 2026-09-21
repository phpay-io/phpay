<?php

namespace PHPay\MercadoPago\Interface;

use PHPay\Contracts\{SupportsCharges, SupportsCustomers, SupportsSubscriptions};
use PHPay\MercadoPago\Resources\Charge\Charge;
use PHPay\MercadoPago\Resources\Customer\Customer;
use PHPay\MercadoPago\Resources\Subscription\Subscription;
use PHPay\Support\Customer as CustomerData;

/**
 * the Mercado Pago gateway offers customers, charges and subscriptions.
 *
 * it deliberately does not declare SupportsWebhooks nor SupportsPixKeys:
 *
 * - webhooks have no CRUD API. they are configured in the "Suas integrações"
 *   panel, or per payment through the notification_url field.
 * - Pix is a billing type of a charge (payment_method_id: "pix"), not a
 *   resource with keys and static QR Codes.
 */
interface MercadoPagoGatewayInterface extends
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

    /**
     * whether the credential in use is a test credential.
     *
     * @return bool
     */
    public function isSandbox(): bool;
}
