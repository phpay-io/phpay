<?php

namespace PHPay\Cielo\Interface;

use PHPay\Cielo\Resources\Charge\Charge;
use PHPay\Cielo\Resources\Subscription\Subscription;
use PHPay\Contracts\{SupportsCharges, SupportsSubscriptions};

/**
 * the Cielo gateway offers charges and recurrences.
 *
 * it is an acquirer, not a gateway, and the shape shows: there is no customer
 * resource — the customer is a field of the sale — so SupportsCustomers is not
 * declared. Neither is SupportsWebhooks (notification is configured in the
 * backoffice or per transaction) nor SupportsPixKeys (Pix is a payment type).
 */
interface CieloGatewayInterface extends
    SupportsCharges,
    SupportsSubscriptions
{
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
