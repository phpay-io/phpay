<?php

namespace PHPay\Asaas\Resources\Charge\Interface;

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
     * @param string $id
     * @return array<array|mixed>
     */
    public function find(string $id): array;

    /**
     * create charge
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * update charge by id
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function update(string $id, array $data): array;

    /**
     * delete charge by id
     *
     * @param string $id
     * @return bool
     */
    public function destroy(string $id): bool;

    /**
     * restore charge by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function restore(string $id): array;

    /**
     * attach an existing gateway customer to the charge.
     *
     * @param string $customerId
     * @return ChargeInterface
     */
    public function setCustomerId(string $customerId): ChargeInterface;

    /**
     * attach a customer to the charge, reusing it when an id is given.
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(array $customer): ChargeInterface;

    /**
     * set charge
     *
     * @param array<mixed> $charge
     * @return ChargeInterface
     */
    public function setCharge(array $charge): ChargeInterface;

    /**
     * set query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * get status charge by id
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function getStatus(string $id): array;

    /**
     * get charge digit line by id
     *
     * @param string $id
     * @return mixed
     */
    public function getDigitableLine(string $id): mixed;

    /**
     * get charge qr code pix by id
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function getQrCodePix(string $id): array;

    /**
     * confirm receipt charge by id
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<array|mixed>
     */
    public function confirmReceipt(string $id, array $data): array;

    /**
     * undo confirm receipt charge by id
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function undoConfirmReceipt(string $id): array;
}
