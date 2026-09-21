<?php

namespace PHPay\Cielo\Resources\Charge\Interface;

use PHPay\Support\Money;

interface ChargeInterface
{
    /**
     * set the whole sale payload
     *
     * @param array<mixed> $sale
     * @return ChargeInterface
     */
    public function setSale(array $sale): ChargeInterface;

    /**
     * set the order identifier of your own system
     *
     * @param string $merchantOrderId
     * @return ChargeInterface
     */
    public function setOrderId(string $merchantOrderId): ChargeInterface;

    /**
     * set the customer of the sale
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(array $customer): ChargeInterface;

    /**
     * pay with Pix
     *
     * @param int $amount amount in cents
     * @return ChargeInterface
     */
    public function setPix(Money|int $amount): ChargeInterface;

    /**
     * pay with boleto
     *
     * @param int $amount amount in cents
     * @param array<mixed> $options
     * @return ChargeInterface
     */
    public function setBoleto(Money|int $amount, array $options = []): ChargeInterface;

    /**
     * pay with a credit card
     *
     * @param int $amount amount in cents
     * @param array<mixed> $card
     * @param int $installments
     * @param bool $capture
     * @return ChargeInterface
     */
    public function setCreditCard(
        Money|int $amount,
        array $card,
        int $installments = 1,
        bool $capture = false
    ): ChargeInterface;

    /**
     * set the idempotency key sent as RequestId
     *
     * @param string $requestId
     * @return ChargeInterface
     */
    public function setRequestId(string $requestId): ChargeInterface;

    /**
     * create the sale
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find a sale by its payment id
     *
     * @param string $paymentId
     * @return array<mixed>
     */
    public function find(string $paymentId): array;

    /**
     * find the sales of one order of your own system
     *
     * @param string $merchantOrderId
     * @return array<mixed>
     */
    public function findByOrderId(string $merchantOrderId): array;

    /**
     * get the status of a sale
     *
     * @param string $paymentId
     * @return int|null
     */
    public function getStatus(string $paymentId): ?int;

    /**
     * get the Pix copy-and-paste code of a sale
     *
     * @param string $paymentId
     * @return string|null
     */
    public function getPixCode(string $paymentId): ?string;

    /**
     * capture a previously authorized sale
     *
     * @param string $paymentId
     * @param int|null $amount amount in cents
     * @return array<mixed>
     */
    public function capture(string $paymentId, Money|int|null $amount = null): array;

    /**
     * cancel or refund a sale
     *
     * @param string $paymentId
     * @param int|null $amount amount in cents
     * @return array<mixed>
     */
    public function cancel(string $paymentId, Money|int|null $amount = null): array;
}
