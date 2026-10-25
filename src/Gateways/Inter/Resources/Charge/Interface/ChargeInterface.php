<?php

namespace PHPay\Inter\Resources\Charge\Interface;

use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{Customer, Money};

interface ChargeInterface
{
    /**
     * set the amount of the charge.
     *
     * @param Money $amount
     * @return ChargeInterface
     */
    public function setAmount(Money $amount): ChargeInterface;

    /**
     * set the Pix key that receives the payment.
     *
     * @param string $key
     * @return ChargeInterface
     */
    public function setKey(string $key): ChargeInterface;

    /**
     * set the debtor.
     *
     * @param Customer|array<mixed> $customer
     * @return ChargeInterface
     * @throws ValidationException
     */
    public function setCustomer(Customer|array $customer): ChargeInterface;

    /**
     * set the text shown to the payer.
     *
     * @param string $description
     * @return ChargeInterface
     */
    public function setDescription(string $description): ChargeInterface;

    /**
     * set how long an immediate charge stays payable.
     *
     * @param int $seconds
     * @return ChargeInterface
     */
    public function setExpiration(int $seconds): ChargeInterface;

    /**
     * turn the charge into one with due date.
     *
     * @param string $date Y-m-d
     * @param int $validityAfterDue
     * @return ChargeInterface
     */
    public function setDueDate(string $date, int $validityAfterDue = 30): ChargeInterface;

    /**
     * set additional information shown to the payer.
     *
     * @param array<string, string> $info
     * @return ChargeInterface
     */
    public function setAdditionalInfo(array $info): ChargeInterface;

    /**
     * set list filters.
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface;

    /**
     * create the charge.
     *
     * @param string|null $txid
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(?string $txid = null): array;

    /**
     * find an immediate charge.
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $txid): array;

    /**
     * find a charge with due date.
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function findDue(string $txid): array;

    /**
     * list immediate charges.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array;

    /**
     * list charges with due date.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAllDue(): array;

    /**
     * revise an immediate charge.
     *
     * @param string $txid
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws ApiException
     */
    public function update(string $txid, array $data): array;

    /**
     * cancel an immediate charge.
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $txid): array;

    /**
     * cancel a charge with due date.
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancelDue(string $txid): array;

    /**
     * find a received Pix by its endToEndId.
     *
     * @param string $endToEndId
     * @return array<mixed>
     * @throws ApiException
     */
    public function findPix(string $endToEndId): array;

    /**
     * list received Pix.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAllPix(): array;

    /**
     * refund a received Pix.
     *
     * @param string $endToEndId
     * @param Money $amount
     * @param string|null $refundId
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $endToEndId, Money $amount, ?string $refundId = null): array;

    /**
     * find a refund.
     *
     * @param string $endToEndId
     * @param string $refundId
     * @return array<mixed>
     * @throws ApiException
     */
    public function findRefund(string $endToEndId, string $refundId): array;
}
