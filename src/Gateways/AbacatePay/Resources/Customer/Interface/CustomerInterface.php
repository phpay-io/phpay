<?php

namespace PHPay\AbacatePay\Resources\Customer\Interface;

interface CustomerInterface
{
    /**
     * create customer
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * list customers
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return CustomerInterface
     */
    public function setQueryParams(array $queryParams): CustomerInterface;
}
