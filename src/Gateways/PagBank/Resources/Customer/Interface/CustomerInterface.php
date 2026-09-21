<?php

namespace PHPay\PagBank\Resources\Customer\Interface;

interface CustomerInterface
{
    /**
     * create subscriber
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find subscriber by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * update subscriber by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function update(string $id): array;

    /**
     * list subscribers
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * set list filter
     *
     * @param array<mixed> $filter
     * @return CustomerInterface
     */
    public function setFilter(array $filter = []): CustomerInterface;
}
