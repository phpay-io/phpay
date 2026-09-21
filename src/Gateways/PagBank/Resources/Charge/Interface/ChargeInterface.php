<?php

namespace PHPay\PagBank\Resources\Charge\Interface;

use PHPay\Support\Money;

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
     * set the customer of the order
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
     * @param string $name
     * @param int $unitAmount amount in cents
     * @param int $quantity
     * @return ChargeInterface
     */
    public function addItem(string $name, Money|int $unitAmount, int $quantity = 1): ChargeInterface;

    /**
     * set the charges of the order (card or boleto)
     *
     * @param array<mixed> $charges
     * @return ChargeInterface
     */
    public function setCharges(array $charges): ChargeInterface;

    /**
     * request a Pix QR Code for the order
     *
     * @param int $amount amount in cents
     * @param string|null $expiresAt
     * @return ChargeInterface
     */
    public function setQrCode(Money|int $amount, ?string $expiresAt = null): ChargeInterface;

    /**
     * set the urls notified about order events
     *
     * @param array<int, string> $urls
     * @return ChargeInterface
     */
    public function setNotificationUrls(array $urls): ChargeInterface;

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
     * refund a charge, fully or partially
     *
     * @param string $id
     * @param int|null $amount amount in cents
     * @return array<mixed>
     */
    public function refund(string $id, Money|int|null $amount = null): array;
}
