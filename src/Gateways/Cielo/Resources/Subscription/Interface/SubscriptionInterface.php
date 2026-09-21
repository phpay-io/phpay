<?php

namespace PHPay\Cielo\Resources\Subscription\Interface;

use PHPay\Cielo\Enums\RecurrentIntervalEnum;

interface SubscriptionInterface
{
    /**
     * set the order identifier of your own system
     *
     * @param string $merchantOrderId
     * @return SubscriptionInterface
     */
    public function setOrderId(string $merchantOrderId): SubscriptionInterface;

    /**
     * set the customer of the recurrence
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(array $customer): SubscriptionInterface;

    /**
     * set the credit card the recurrence charges
     *
     * @param array<mixed> $card
     * @return SubscriptionInterface
     */
    public function setCard(array $card): SubscriptionInterface;

    /**
     * set how often the recurrence charges
     *
     * @param RecurrentIntervalEnum $interval
     * @return SubscriptionInterface
     */
    public function setInterval(RecurrentIntervalEnum $interval): SubscriptionInterface;

    /**
     * set when the recurrence stops
     *
     * @param string $endDate
     * @return SubscriptionInterface
     */
    public function setEndDate(string $endDate): SubscriptionInterface;

    /**
     * create the recurrence
     *
     * @param int $amount amount in cents
     * @return array<mixed>
     */
    public function create(int $amount): array;

    /**
     * find a recurrence by id
     *
     * @param string $recurrentPaymentId
     * @return array<mixed>
     */
    public function find(string $recurrentPaymentId): array;

    /**
     * suspend a recurrence
     *
     * @param string $recurrentPaymentId
     * @return array<mixed>
     */
    public function deactivate(string $recurrentPaymentId): array;

    /**
     * resume a suspended recurrence
     *
     * @param string $recurrentPaymentId
     * @return array<mixed>
     */
    public function reactivate(string $recurrentPaymentId): array;

    /**
     * change the charged amount
     *
     * @param string $recurrentPaymentId
     * @param int $amount amount in cents
     * @return array<mixed>
     */
    public function updateAmount(string $recurrentPaymentId, int $amount): array;

    /**
     * change how often the recurrence charges
     *
     * @param string $recurrentPaymentId
     * @param RecurrentIntervalEnum $interval
     * @return array<mixed>
     */
    public function updateInterval(string $recurrentPaymentId, RecurrentIntervalEnum $interval): array;

    /**
     * change when the recurrence stops
     *
     * @param string $recurrentPaymentId
     * @param string $endDate
     * @return array<mixed>
     */
    public function updateEndDate(string $recurrentPaymentId, string $endDate): array;

    /**
     * change the date of the next charge
     *
     * @param string $recurrentPaymentId
     * @param string $nextPaymentDate
     * @return array<mixed>
     */
    public function updateNextPaymentDate(string $recurrentPaymentId, string $nextPaymentDate): array;
}
