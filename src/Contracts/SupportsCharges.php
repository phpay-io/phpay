<?php

namespace PHPay\Contracts;

/**
 * the gateway can create and manage charges.
 */
interface SupportsCharges extends GatewayInterface
{
    /**
     * get resource charge from gateway.
     *
     * @return object
     */
    public function charge(): object;
}
