<?php

namespace PHPay\Rede\Resources\Charge\Interface;

use PHPay\Rede\Enums\TransactionKindEnum;

interface ChargeInterface
{
    /**
     * set the whole transaction payload
     *
     * @param array<mixed> $transaction
     * @return ChargeInterface
     */
    public function setTransaction(array $transaction): ChargeInterface;

    /**
     * set the order identifier of your own system
     *
     * @param string $reference
     * @return ChargeInterface
     */
    public function setReference(string $reference): ChargeInterface;

    /**
     * set the card being charged
     *
     * @param string $number
     * @param string $holderName
     * @param string $expirationMonth
     * @param string $expirationYear
     * @param string $securityCode
     * @return ChargeInterface
     */
    public function setCard(
        string $number,
        string $holderName,
        string $expirationMonth,
        string $expirationYear,
        string $securityCode
    ): ChargeInterface;

    /**
     * set how the card is charged
     *
     * @param int $amount amount in cents
     * @param TransactionKindEnum $kind
     * @param int $installments
     * @param bool $capture true authorizes and captures in one step
     * @return ChargeInterface
     */
    public function setPayment(
        int $amount,
        TransactionKindEnum $kind = TransactionKindEnum::CREDIT,
        int $installments = 1,
        bool $capture = true
    ): ChargeInterface;

    /**
     * set what shows on the cardholder statement
     *
     * @param string $softDescriptor
     * @return ChargeInterface
     */
    public function setSoftDescriptor(string $softDescriptor): ChargeInterface;

    /**
     * create the transaction
     *
     * @return array<mixed>
     */
    public function create(): array;

    /**
     * find a transaction by its tid
     *
     * @param string $tid
     * @return array<mixed>
     */
    public function find(string $tid): array;

    /**
     * find a transaction by the reference of your own system
     *
     * @param string $reference
     * @return array<mixed>
     */
    public function findByReference(string $reference): array;

    /**
     * get the return code of a transaction
     *
     * @param string $tid
     * @return string|null
     */
    public function getStatus(string $tid): ?string;

    /**
     * capture a previously authorized transaction
     *
     * @param string $tid
     * @param int|null $amount amount in cents
     * @return array<mixed>
     */
    public function capture(string $tid, ?int $amount = null): array;

    /**
     * refund a transaction, fully or partially
     *
     * @param string $tid
     * @param int|null $amount amount in cents
     * @return array<mixed>
     */
    public function refund(string $tid, ?int $amount = null): array;
}
