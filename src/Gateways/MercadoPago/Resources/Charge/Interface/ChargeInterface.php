<?php

namespace PHPay\MercadoPago\Resources\Charge\Interface;

use PHPay\Support\{Customer as CustomerData, Money};

interface ChargeInterface
{
    /**
     * set charge payload
     *
     * @param array<mixed> $charge
     * @return ChargeInterface
     */
    public function setCharge(array $charge): ChargeInterface;

    /**
     * set the amount of the charge, in reais
     *
     * @param Money|int|float $amount
     * @return ChargeInterface
     */
    public function setAmount(Money|int|float $amount): ChargeInterface;

    /**
     * set the payer of the charge
     *
     * @param array<mixed> $payer
     * @return ChargeInterface
     */
    public function setPayer(CustomerData|array $payer): ChargeInterface;

    /**
     * set the idempotency key used on create
     *
     * @param string $key
     * @return ChargeInterface
     */
    public function setIdempotencyKey(string $key): ChargeInterface;

    /**
     * set search query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * create charge
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find charge by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * search charges
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * get charge status
     *
     * @param string $id
     * @return string|null
     */
    public function getStatus(string $id): ?string;

    /**
     * get the pix copy-and-paste code of a charge
     *
     * @param string $id
     * @return string|null
     */
    public function getPixCode(string $id): ?string;

    /**
     * cancel charge
     *
     * @param string $id
     * @return array<mixed>
     */
    public function cancel(string $id): array;

    /**
     * refund charge, fully or partially
     *
     * @param string $id
     * @param float|null $value
     * @return array<mixed>
     */
    public function refund(string $id, ?float $value = null): array;
}
