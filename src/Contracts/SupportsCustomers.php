<?php

namespace PHPay\Contracts;

use PHPay\Support\Customer as CustomerData;

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
    public function customer(CustomerData|array $customer = []): object;
}
