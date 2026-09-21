<?php

namespace PHPay\Contracts;

/**
 * base contract for every gateway.
 *
 * it carries identity only. what a gateway can actually do is declared by the
 * capability interfaces it implements — SupportsCustomers, SupportsCharges,
 * SupportsWebhooks, SupportsPixKeys and SupportsSubscriptions — so a gateway
 * never has to declare a resource it does not offer.
 */
interface GatewayInterface
{
    /**
     * human readable gateway name, used in messages.
     *
     * @return string
     */
    public function name(): string;
}
