<?php

namespace PHPay\Woovi\Resources\Pix\Interface;

use PHPay\Support\Money;
use PHPay\Woovi\Enums\PixKeyTypeEnum;

interface PixInterface
{
    /**
     * register a Pix key on the account
     *
     * @param PixKeyTypeEnum $type
     * @param string|null $key null for EVP, whose key the bank generates
     * @return array<mixed>
     */
    public function createKey(PixKeyTypeEnum $type, ?string $key = null): array;

    /**
     * list the Pix keys of the account
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * look a Pix key up before paying it
     *
     * @param string $key
     * @return array<mixed>
     */
    public function verifyKey(string $key): array;

    /**
     * create a static QR Code
     *
     * @param string $name
     * @param int|null $value amount in cents; null lets the payer choose
     * @param string|null $correlationId
     * @return array<mixed>
     */
    public function staticQrCode(string $name, Money|int|null $value = null, ?string $correlationId = null): array;

    /**
     * list static QR Codes
     *
     * @return array<mixed>
     */
    public function getAllStaticQrCodes(): array;

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return PixInterface
     */
    public function setQueryParams(array $queryParams): PixInterface;
}
