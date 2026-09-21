<?php

namespace PHPay\PagarMe\Resources\Customer\Interface;

interface CustomerInterface
{
    /**
     * create customer
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find customer by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * update customer by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function update(string $id): array;

    /**
     * list customers
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * list the saved cards of a customer
     *
     * @param string $id
     * @return array<mixed>
     */
    public function cards(string $id): array;

    /**
     * set list filter
     *
     * @param array<mixed> $filter
     * @return CustomerInterface
     */
    public function setFilter(array $filter = []): CustomerInterface;
}
