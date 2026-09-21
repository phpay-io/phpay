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
     * unix time after which the token is considered stale
     */
    private int $tokenExpiresAt = 0;

    /**
     * seconds subtracted from the advertised lifetime, so a token never
     * expires between being handed out and being used
     */
    private const EXPIRY_MARGIN = 30;

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
     * get token, authorizing on first use and again once it expires.
     *
     * the gateway may outlive the token — a queue worker keeps the same
     * instance for hours — so the lifetime Efí advertises is honored.
     *
     * @return array<string, mixed> token
     * @throws ApiException
     */
    public function getToken(): array
    {
        if ($this->token === null || time() >= $this->tokenExpiresAt) {
            $this->token          = $this->authorize();
            $this->tokenExpiresAt = self::expiresAt($this->token);
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

    /**
     * unix time at which a token stops being reused.
     *
     * without `expires_in` the token is kept for the life of the instance,
     * which is what the gateway always did.
     *
     * @param array<string, mixed> $token
     * @return int
     */
    private static function expiresAt(array $token): int
    {
        $lifetime = $token['expires_in'] ?? null;

        return is_numeric($lifetime)
            ? time() + (int) $lifetime - self::EXPIRY_MARGIN
            : PHP_INT_MAX;
    }
}
