<?php

namespace PHPay\Inter\Resources\Authorization;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;
use PHPay\Inter\Resources\Authorization\Interface\AuthorizationInterface;
use PHPay\Inter\Traits\HasInterClient;

/**
 * OAuth2 client_credentials against the Inter token endpoint.
 *
 * two things make this one different from every other token here.
 *
 * **The token is issued for a set of scopes**, and `scope` is required: there
 * is no "all my permissions" token. Each endpoint documents the scope it
 * needs, so the gateway asks for the union of what its capabilities call —
 * asking for a scope the application was not granted fails the whole request.
 *
 * **Generating one is rate limited to five calls per minute.** That turns
 * caching from an optimization into a requirement: a process that asked for a
 * token per request would be refused with `429` by the sixth one. The
 * documentation says so plainly — one token, reused for an hour.
 */
class Authorization implements AuthorizationInterface
{
    /**
     * trait Inter client
     */
    use HasInterClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * construct
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $scopes space-separated, as the OAuth spec writes them
     * @param Certificate|null $certificate required unless a client is injected
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     * @throws ValidationException
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $clientId,
        #[\SensitiveParameter]
        private string $clientSecret,
        private string $scopes,
        ?Certificate $certificate = null,
        protected bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientInterAuthorize($certificate);
    }

    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed> `access_token`, `token_type`, `expires_in`, `scope`
     * @throws ApiException
     */
    public function getToken(): array
    {
        /*
         * form_params, not json: the token endpoint reads
         * application/x-www-form-urlencoded, and the credentials go in the body
         * rather than in an Authorization header.
         */
        return $this->request('POST', 'oauth/v2/token', [
            'form_params' => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'client_credentials',
                'scope'         => $this->scopes,
            ],
        ]);
    }
}
