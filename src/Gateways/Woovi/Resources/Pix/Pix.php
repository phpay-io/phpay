<?php

namespace PHPay\Woovi\Resources\Pix;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Woovi\Enums\PixKeyTypeEnum;
use PHPay\Woovi\Requests\WooviPixKeyRequest;
use PHPay\Woovi\Resources\Pix\Interface\PixInterface;
use PHPay\Woovi\Traits\HasWooviClient;

/**
 * Pix keys and static QR Codes of the Woovi/OpenPix API.
 *
 * this is what SupportsPixKeys means, and Woovi is the second gateway in the
 * library able to offer it — only a PSP that issues its own keys can.
 */
class Pix implements PixInterface
{
    /**
     * trait woovi client
     */
    use HasWooviClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * construct
     *
     * @param string $appId
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $appId,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientWooviBoot();
    }

    /**
     * register a Pix key on the account.
     *
     * PHONE and EMAIL need extra permission on the account.
     *
     * @param PixKeyTypeEnum $type
     * @param string|null $key null for EVP, whose key the bank generates
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createKey(PixKeyTypeEnum $type, ?string $key = null): array
    {
        $payload = ['type' => $type->value];

        if ($key !== null) {
            $payload['key'] = $key;
        }

        WooviPixKeyRequest::validate($payload);

        return $this->post('api/v1/pix-keys', $payload);
    }

    /**
     * list the Pix keys of the account
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('api/v1/pix-keys', $this->queryParams);
    }

    /**
     * look a Pix key up before paying it.
     *
     * returns the owner data and the pixKeyEndToEndId a payout needs.
     *
     * @param string $key
     * @return array<mixed>
     * @throws ApiException
     */
    public function verifyKey(string $key): array
    {
        return $this->get('api/v1/pix-key-check/' . rawurlencode($key));
    }

    /**
     * create a static QR Code
     *
     * @param string $name
     * @param int|null $value amount in cents; null lets the payer choose
     * @param string|null $correlationId
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function staticQrCode(string $name, ?int $value = null, ?string $correlationId = null): array
    {
        $payload = ['name' => $name];

        if ($value !== null) {
            $payload['value'] = $value;
        }

        if ($correlationId !== null) {
            $payload['correlationID'] = $correlationId;
        }

        WooviPixKeyRequest::validateStaticQrCode($payload);

        return $this->post('api/v1/pixQrCode', $payload);
    }

    /**
     * list static QR Codes
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAllStaticQrCodes(): array
    {
        return $this->get('api/v1/pixQrCode', $this->queryParams);
    }

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return PixInterface
     */
    public function setQueryParams(array $queryParams): PixInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }
}
