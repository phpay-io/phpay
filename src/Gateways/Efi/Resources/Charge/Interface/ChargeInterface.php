<?php

namespace Efi\Resources\Charge\Interface;

use Efi\Resources\Charge\Charge;

interface ChargeInterface
{
    /**
     * get all charges
     *
     * @return array<array|mixed>
     */
    public function getAll(): array;

    /**
     * find charge by id
     *
     * @param string  $id
     * @return array<array|mixed>
     */
    public function find(string $id): array;

    /**
     * set customer
     *
     * @param array<mixed> $customer
     * @return Charge
     */
    public function setCustomer(array $customer): Charge;

    /**
     * set query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * @param string $id
     * @return array<array|mixed>
     */
    public function confirmReceipt(string $id): array;

    /**
     * delete charge by id
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function destroy(string $id): array;

    /**
     * cancel charge by id
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function cancel(string $id): array;
}
