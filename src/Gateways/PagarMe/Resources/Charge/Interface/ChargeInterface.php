<?php

namespace PHPay\PagarMe\Resources\Charge\Interface;

interface ChargeInterface
{
    /**
     * set the whole order payload
     *
     * @param array<mixed> $order
     * @return ChargeInterface
     */
    public function setOrder(array $order): ChargeInterface;

    /**
     * attach an existing customer to the order
     *
     * @param string $customerId
     * @return ChargeInterface
     */
    public function setCustomerId(string $customerId): ChargeInterface;

    /**
     * attach a customer created along with the order
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(array $customer): ChargeInterface;

    /**
     * set the items of the order
     *
     * @param array<mixed> $items
     * @return ChargeInterface
     */
    public function setItems(array $items): ChargeInterface;

    /**
     * append a single item to the order
     *
     * @param string $description
     * @param int $amount amount in cents
     * @param int $quantity
     * @return ChargeInterface
     */
    public function addItem(string $description, int $amount, int $quantity = 1): ChargeInterface;

    /**
     * set the payments of the order
     *
     * @param array<mixed> $payments
     * @return ChargeInterface
     */
    public function setPayments(array $payments): ChargeInterface;

    /**
     * pay the order with Pix
     *
     * @param int $expiresIn seconds until the QR Code expires
     * @return ChargeInterface
     */
    public function setPix(int $expiresIn = 3600): ChargeInterface;

    /**
     * pay the order with boleto
     *
     * @param string|null $dueAt
     * @param array<mixed> $instructions
     * @return ChargeInterface
     */
    public function setBoleto(?string $dueAt = null, array $instructions = []): ChargeInterface;

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * create the order
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find order by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * list orders
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * find charge by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function findCharge(string $id): array;

    /**
     * get the status of a charge
     *
     * @param string $id
     * @return string|null
     */
    public function getStatus(string $id): ?string;

    /**
     * get the Pix copy-and-paste code of an order
     *
     * @param string $id
     * @return string|null
     */
    public function getPixCode(string $id): ?string;

    /**
     * capture a previously authorized charge
     *
     * @param string $id
     * @param int|null $amount amount in cents
     * @return array<mixed>
     */
    public function capture(string $id, ?int $amount = null): array;

    /**
     * cancel a charge, refunding fully or partially
     *
     * @param string $id
     * @param int|null $amount amount in cents
     * @return array<mixed>
     */
    public function cancel(string $id, ?int $amount = null): array;
}
