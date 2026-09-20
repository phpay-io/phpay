<?php

namespace PHPay\MercadoPago\Resources\Customer\Interface;

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
     * search customers
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * find a customer by e-mail
     *
     * @param string $email
     * @return array<mixed>|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * set search filter
     *
     * @param array<mixed> $filter
     * @return CustomerInterface
     */
    public function setFilter(array $filter = []): CustomerInterface;
}
