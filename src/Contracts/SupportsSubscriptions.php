<?php

namespace PHPay\Contracts;

/**
 * the gateway supports recurring billing.
 */
interface SupportsSubscriptions extends GatewayInterface
{
    /**
     * get resource subscription from gateway.
     *
     * @return object
     */
    public function subscription(): object;
}
