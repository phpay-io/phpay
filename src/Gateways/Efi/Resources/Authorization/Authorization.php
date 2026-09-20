<?php

namespace PHPay\Efi\Resources\Authorization;

use GuzzleHttp\Client;
use PHPay\Efi\Resources\Authorization\Interface\AuthorizationInterface;
use PHPay\Efi\Traits\HasEfiClient;
use PHPay\Exceptions\ApiException;

class Authorization implements AuthorizationInterface
{
    /**
     * trait Efi client
     */
    use HasEfiClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * construct
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        protected bool $sandbox = true,
        ?Client $client = null
    ) {
        $this->client = $client ?? $this->clientEfiAuthorize($clientId, $clientSecret);
    }

    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function getToken(): array
    {
        return $this->post('v1/authorize', [
            'grant_type' => 'client_credentials',
        ]);
    }
}
