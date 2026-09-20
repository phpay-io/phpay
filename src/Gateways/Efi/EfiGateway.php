<?php

namespace PHPay\Efi;

use GuzzleHttp\Client;
use PHPay\Efi\Interface\EfiGatewayInterface;
use PHPay\Efi\Resources\Authorization\Authorization;
use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Exceptions\ApiException;

class EfiGateway implements EfiGatewayInterface
{
    /**
     * credentials exchanged for an access token
     *
     * @var array<string, mixed>|null
     */
    private ?array $token = null;

    /**
     * construct
     *
     * no network call happens here — the token is fetched lazily on first use.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $sandbox = true,
        private ?Client $client = null,
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Efí';
    }

    /**
     * get token, authorizing on first use.
     *
     * @return array<string, mixed> token
     * @throws ApiException
     */
    public function getToken(): array
    {
        if ($this->token === null) {
            $this->token = $this->authorize();
        }

        return $this->token;
    }

    /**
     * create charge
     *
     * @param array<mixed> $charge
     * @return Charge
     * @throws ApiException
     */
    public function charge(array $charge = []): Charge
    {
        return new Charge(
            $this->getToken(),
            $charge,
            $this->sandbox,
            $this->client
        );
    }

    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    private function authorize(): array
    {
        $token = (new Authorization(
            $this->clientId,
            $this->clientSecret,
            $this->sandbox,
            $this->client
        ))->getToken();

        if (!isset($token['access_token']) || !isset($token['token_type'])) {
            throw new ApiException(
                'Efí: autorização não retornou access_token.',
                'Efí',
                0,
                $token
            );
        }

        return $token;
    }
}
