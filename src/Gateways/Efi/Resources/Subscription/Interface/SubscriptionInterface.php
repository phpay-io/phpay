<?php

namespace PHPay\Efi\Resources\Subscription\Interface;

use PHPay\Efi\Enums\{AccountTypeEnum, PeriodicityEnum};
use PHPay\Support\{Customer, Money};

interface SubscriptionInterface
{
    /**
     * set the debtor
     *
     * @param Customer|array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(Customer|array $customer): SubscriptionInterface;

    /**
     * set the contract the recurrence is bound to
     *
     * @param string $contract
     * @return SubscriptionInterface
     */
    public function setContract(string $contract): SubscriptionInterface;

    /**
     * set what is being charged
     *
     * @param string $description
     * @return SubscriptionInterface
     */
    public function setDescription(string $description): SubscriptionInterface;

    /**
     * set a fixed amount for every cycle
     *
     * @param Money $amount
     * @return SubscriptionInterface
     */
    public function setAmount(Money $amount): SubscriptionInterface;

    /**
     * set the minimum of a variable amount
     *
     * @param Money $amount
     * @return SubscriptionInterface
     */
    public function setMinimumAmount(Money $amount): SubscriptionInterface;

    /**
     * set the calendar of the recurrence
     *
     * @param PeriodicityEnum $periodicity
     * @param string $startDate
     * @param string|null $endDate
     * @return SubscriptionInterface
     */
    public function setPeriodicity(
        PeriodicityEnum $periodicity,
        string $startDate,
        ?string $endDate = null
    ): SubscriptionInterface;

    /**
     * allow retries after a failed charge
     *
     * @param bool $allow
     * @return SubscriptionInterface
     */
    public function allowRetries(bool $allow = true): SubscriptionInterface;

    /**
     * bind the recurrence to a location, for the QR Code journey
     *
     * @param int $locationId
     * @return SubscriptionInterface
     */
    public function setLocation(int $locationId): SubscriptionInterface;

    /**
     * activate the recurrence together with an immediate charge
     *
     * @param string $txid
     * @return SubscriptionInterface
     */
    public function setActivationTxid(string $txid): SubscriptionInterface;

    /**
     * set the account that receives the charges
     *
     * @param string $account
     * @param AccountTypeEnum $type
     * @param string|null $branch
     * @return SubscriptionInterface
     */
    public function setReceiver(
        string $account,
        AccountTypeEnum $type = AccountTypeEnum::CHECKING,
        ?string $branch = null
    ): SubscriptionInterface;

    /**
     * set list filters
     *
     * @param array<mixed> $queryParams
     * @return SubscriptionInterface
     */
    public function setQueryParams(array $queryParams): SubscriptionInterface;

    /**
     * create the recurrence
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find a recurrence
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * list recurrences
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * revise a recurrence
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function update(string $id, array $data): array;

    /**
     * cancel a recurrence
     *
     * @param string $id
     * @return array<mixed>
     */
    public function cancel(string $id): array;

    /**
     * create a location for the QR Code journey
     *
     * @return array<mixed>
     */
    public function createLocation(): array;

    /**
     * create the charge of one cycle
     *
     * @param string $id
     * @param Money $amount
     * @param string $dueDate
     * @param array<mixed> $extra
     * @return array<mixed>
     */
    public function createCharge(string $id, Money $amount, string $dueDate, array $extra = []): array;

    /**
     * find the charge of a cycle
     *
     * @param string $txid
     * @return array<mixed>
     */
    public function findCharge(string $txid): array;

    /**
     * cancel the charge of a cycle
     *
     * @param string $txid
     * @return array<mixed>
     */
    public function cancelCharge(string $txid): array;
}
