<?php

namespace PHPay\Efi\Resources\PixAuthorization;

use GuzzleHttp\Client;
use PHPay\Efi\Traits\HasEfiPixClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;

/**
 * token of the Efí Pix API.
 *
 * a different token from the Cobranças one, on a different path
 * (`oauth/token`, not `v1/authorize`), and — unlike it — only over mTLS.
 */
class PixAuthorization
{
    /**
     * trait Efí Pix client
     */
    use HasEfiPixClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * construct
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param Certificate|null $certificate required unless a client is injected
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     * @throws ValidationException
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        ?Certificate $certificate = null,
        protected bool $sandbox = true,
        ?Client $client = null
    ) {
        $this->client = $client ?? $this->clientEfiPixAuthorize($clientId, $clientSecret, $certificate);
    }

    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function getToken(): array
    {
        return $this->post('oauth/token', [
            'grant_type' => 'client_credentials',
        ]);
    }
}
