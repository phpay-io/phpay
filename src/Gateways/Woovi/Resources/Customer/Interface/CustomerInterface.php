<?php

namespace PHPay\Woovi\Resources\Customer\Interface;

interface CustomerInterface
{
    /**
     * create customer
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find a customer by correlationID or by the gateway id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

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
