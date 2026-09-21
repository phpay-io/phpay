<?php

namespace PHPay\Rede;

use GuzzleHttp\Client;
use PHPay\Rede\Interface\RedeGatewayInterface;
use PHPay\Rede\Resources\Authorization\Authorization;
use PHPay\Rede\Resources\Charge\Charge;

class RedeGateway implements RedeGatewayInterface
{
    /**
     * authorization shared by every resource, so the token is negotiated once
     */
    private ?Authorization $authorization = null;

    /**
     * construct
     *
     * no network call happens here — the token is negotiated on first use.
     *
     * @param string $affiliation PV
     * @param string $secret token
     * @param bool $sandbox
     * @param Client|null $client injected API client, mainly for tests
     * @param Client|null $oauthClient injected OAuth client, mainly for tests
     */
    public function __construct(
        private string $affiliation,
        private string $secret,
        private bool $sandbox = true,
        private ?Client $client = null,
        private ?Client $oauthClient = null,
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Rede';
    }

    /**
     * the OAuth authorization, built once and shared by every resource.
     *
     * @return Authorization
     */
    public function authorization(): Authorization
    {
        if ($this->authorization === null) {
            $this->authorization = new Authorization(
                $this->affiliation,
                $this->secret,
                RedeEnvironment::tokenPath($this->sandbox),
                $this->oauthClient ?? new Client([
                    'base_uri' => RedeEnvironment::oauth($this->sandbox),
                    'headers'  => ['accept' => 'application/json', 'user-agent' => 'PHPay'],
                ])
            );
        }

        return $this->authorization;
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->authorization(), $this->sandbox, $this->client);
    }
}
