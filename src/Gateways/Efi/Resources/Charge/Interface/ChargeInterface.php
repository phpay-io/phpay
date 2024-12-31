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
     * cancel charge by id
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function cancel(string $id): array;

    /**
     * @param string $id
     * @param string $dueDate
     * @return array<array|mixed>
     */
    public function updateDueDate(string $id, string $dueDate): array;

    /**
     * update billet metadata
     * notification_url and custom_id
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function updateMetadata(string $id, array $data): array;
}
