<?php

namespace PHPay\Contracts;

/**
 * the gateway exposes customers as a resource of their own.
 */
interface SupportsCustomers extends GatewayInterface
{
    /**
     * get resource customer from gateway.
     *
     * @param array<mixed> $customer
     * @return object
     */
    public function customer(array $customer = []): object;
}
