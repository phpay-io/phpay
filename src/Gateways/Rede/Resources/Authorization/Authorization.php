<?php

namespace PHPay\Rede\Resources\Authorization;

use GuzzleHttp\Client;
use PHPay\Exceptions\ApiException;
use PHPay\Http\HasHttpClient;

/**
 * OAuth2 client_credentials against the Rede authorization host.
 *
 * Unlike every other gateway here, the Rede token expires: the response
 * carries `expires_in`, so a long-running process has to renegotiate. This
 * class owns that lifecycle and hands out a token that is still valid.
 */
class Authorization
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * client guzzle, pointed at the OAuth host
     */
    private Client $client;

    /**
     * token currently in hand
     */
    private ?string $token = null;

    /**
     * unix time after which the token is considered stale
     */
    private int $expiresAt = 0;

    /**
     * seconds subtracted from the advertised lifetime, so a token never
     * expires between being handed out and being used
     */
    private const EXPIRY_MARGIN = 30;

    /**
     * construct
     *
     * @param string $affiliation PV
     * @param string $secret token
     * @param string $tokenPath differs between sandbox and production
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $affiliation,
        private string $secret,
        private string $tokenPath,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    /**
     * get a valid access token, renegotiating only when needed.
     *
     * @return string
     * @throws ApiException
     */
    public function token(): string
    {
        if ($this->token !== null && time() < $this->expiresAt) {
            return $this->token;
        }

        return $this->negotiate();
    }

    /**
     * whether a token is held and still valid.
     *
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return $this->token !== null && time() < $this->expiresAt;
    }

    /**
     * drop the token in hand, forcing the next call to renegotiate.
     *
     * @return void
     */
    public function forget(): void
    {
        $this->token     = null;
        $this->expiresAt = 0;
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Rede';
    }

    /**
     * exchange the credentials for a fresh token.
     *
     * @return string
     * @throws ApiException
     */
    private function negotiate(): string
    {
        $response = $this->request('POST', $this->tokenPath, [
            'form_params' => ['grant_type' => 'client_credentials'],
            'headers'     => [
                'Authorization' => 'Basic ' . base64_encode("{$this->affiliation}:{$this->secret}"),
                'content-type'  => 'application/x-www-form-urlencoded',
            ],
        ]);

        $token = $response['access_token'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new ApiException(
                'Rede: a autorização não retornou access_token.',
                'Rede',
                0,
                $response
            );
        }

        $expiresIn = $response['expires_in'] ?? null;

        $lifetime = is_numeric($expiresIn) ? (int) $expiresIn : 300;

        $this->token     = $token;
        $this->expiresAt = time() + max(0, $lifetime - self::EXPIRY_MARGIN);

        return $token;
    }
}
