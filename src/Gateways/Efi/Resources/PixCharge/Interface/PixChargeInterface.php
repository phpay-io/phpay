<?php

namespace PHPay\Efi\Resources\PixCharge\Interface;

use PHPay\Support\{Customer, Money};

interface PixChargeInterface
{
    /**
     * set the amount of the charge
     *
     * @param Money $amount
     * @return PixChargeInterface
     */
    public function setAmount(Money $amount): PixChargeInterface;

    /**
     * set the Pix key that receives the payment
     *
     * @param string $key
     * @return PixChargeInterface
     */
    public function setKey(string $key): PixChargeInterface;

    /**
     * set the debtor
     *
     * @param Customer|array<mixed> $customer
     * @return PixChargeInterface
     */
    public function setCustomer(Customer|array $customer): PixChargeInterface;

    /**
     * set the text shown to the payer
     *
     * @param string $description
     * @return PixChargeInterface
     */
    public function setDescription(string $description): PixChargeInterface;

    /**
     * set how long an immediate charge stays payable
     *
     * @param int $seconds
     * @return PixChargeInterface
     */
    public function setExpiration(int $seconds): PixChargeInterface;

    /**
     * turn the charge into a charge with due date
     *
     * @param string $date
     * @param int $validityAfterDue
     * @return PixChargeInterface
     */
    public function setDueDate(string $date, int $validityAfterDue = 30): PixChargeInterface;

    /**
     * set additional information shown to the payer
     *
     * @param array<string, string> $info
     * @return PixChargeInterface
     */
    public function setAdditionalInfo(array $info): PixChargeInterface;

    /**
     * set list filters
     *
     * @param array<mixed> $queryParams
     * @return PixChargeInterface
     */
    public function setQueryParams(array $queryParams): PixChargeInterface;

    /**
     * create the charge
     *
     * @param string|null $txid
     * @return array<mixed>
     */
    public function create(?string $txid = null): array;

    /**
     * find an immediate charge
     *
     * @param string $txid
     * @return array<mixed>
     */
    public function find(string $txid): array;

    /**
     * find a charge with due date
     *
     * @param string $txid
     * @return array<mixed>
     */
    public function findDue(string $txid): array;

    /**
     * list immediate charges
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * list charges with due date
     *
     * @return array<mixed>
     */
    public function getAllDue(): array;

    /**
     * revise an immediate charge
     *
     * @param string $txid
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function update(string $txid, array $data): array;

    /**
     * cancel an immediate charge
     *
     * @param string $txid
     * @return array<mixed>
     */
    public function cancel(string $txid): array;

    /**
     * cancel a charge with due date
     *
     * @param string $txid
     * @return array<mixed>
     */
    public function cancelDue(string $txid): array;

    /**
     * QR Code and copy-and-paste code of a charge
     *
     * @param int $locationId
     * @return array<mixed>
     */
    public function qrCode(int $locationId): array;

    /**
     * refund a received Pix
     *
     * @param string $endToEndId
     * @param Money $amount
     * @param string|null $refundId
     * @return array<mixed>
     */
    public function refund(string $endToEndId, Money $amount, ?string $refundId = null): array;

    /**
     * find a refund
     *
     * @param string $endToEndId
     * @param string $refundId
     * @return array<mixed>
     */
    public function findRefund(string $endToEndId, string $refundId): array;
}
