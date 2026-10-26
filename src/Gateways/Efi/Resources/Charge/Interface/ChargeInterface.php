<?php

namespace PHPay\Efi\Resources\Charge\Interface;

use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Exceptions\ValidationException;
use PHPay\Support\{Customer, Money};

interface ChargeInterface
{
    /**
     * set the amount of the charge, in cents
     *
     * @param Money|int $amount
     * @return ChargeInterface
     */
    public function setAmount(Money|int $amount): ChargeInterface;

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
    public function setCustomer(Customer|array $customer): Charge;

    /**
     * set query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * set the items of the charge, for billing more than one line.
     *
     * each item needs `name` and `value` in **cents**.
     *
     * @param array<mixed> $items
     * @return ChargeInterface
     * @throws ValidationException
     */
    public function setItems(array $items): ChargeInterface;

    /**
     * set fine, interest and automatic write-off of the billet.
     *
     * the scales differ: `fine` is percent × 100 and `interest` is
     * percent × 1000, both as integers.
     *
     * @param array<mixed> $configurations
     * @return ChargeInterface
     * @throws ValidationException
     */
    public function setConfigurations(array $configurations): ChargeInterface;

    /**
     * get status charge by id
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function getStatus(string $id): array;

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
