<?php

namespace PHPay\Woovi\Resources\Charge\Interface;

use PHPay\Support\{Customer as CustomerData, Money};

interface ChargeInterface
{
    /**
     * set the whole charge payload
     *
     * @param array<mixed> $charge
     * @return ChargeInterface
     */
    public function setCharge(array $charge): ChargeInterface;

    /**
     * set the identifier of this charge in your own system
     *
     * @param string $correlationId
     * @return ChargeInterface
     */
    public function setCorrelationId(string $correlationId): ChargeInterface;

    /**
     * set the customer of the charge
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(CustomerData|array $customer): ChargeInterface;

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * create the charge
     *
     * @param int $value amount in cents
     * @return array<mixed>
     */
    public function create(Money|int $value): array;

    /**
     * find a charge by correlationID or by the gateway id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * list charges
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * delete a charge
     *
     * @param string $id
     * @return array<mixed>
     */
    public function destroy(string $id): array;

    /**
     * get the Pix copy-and-paste code of a created charge
     *
     * @param array<mixed> $charge
     * @return string|null
     */
    public function getPixCode(array $charge): ?string;
}
