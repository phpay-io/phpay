<?php

namespace PHPay\AbacatePay\Resources\Charge\Interface;

use PHPay\Support\Money;

interface ChargeInterface
{
    /**
     * set the whole billing payload
     *
     * @param array<mixed> $billing
     * @return ChargeInterface
     */
    public function setBilling(array $billing): ChargeInterface;

    /**
     * attach an existing customer to the billing
     *
     * @param string $customerId
     * @return ChargeInterface
     */
    public function setCustomerId(string $customerId): ChargeInterface;

    /**
     * attach a customer created along with the billing
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(array $customer): ChargeInterface;

    /**
     * set the products being charged
     *
     * @param array<mixed> $products
     * @return ChargeInterface
     */
    public function setProducts(array $products): ChargeInterface;

    /**
     * append a single product to the billing
     *
     * @param string $externalId id of the product in your own system
     * @param string $name
     * @param int $price price per unit in cents, minimum 100
     * @param int $quantity
     * @param string|null $description
     * @return ChargeInterface
     */
    public function addProduct(
        string $externalId,
        string $name,
        Money|int $price,
        int $quantity = 1,
        ?string $description = null
    ): ChargeInterface;

    /**
     * set where the customer goes after paying, and if they give up
     *
     * @param string $completionUrl
     * @param string $returnUrl
     * @return ChargeInterface
     */
    public function setUrls(string $completionUrl, string $returnUrl): ChargeInterface;

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * create the billing
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * list billings
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * get the payment link of a created billing
     *
     * @param array<mixed> $billing
     * @return string|null
     */
    public function getPaymentUrl(array $billing): ?string;

    /**
     * whether a created billing was made with a dev mode key
     *
     * @param array<mixed> $billing
     * @return bool|null
     */
    public function isDevMode(array $billing): ?bool;
}
