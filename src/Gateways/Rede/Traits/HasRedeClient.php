<?php

namespace PHPay\Rede\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;
use PHPay\Rede\RedeEnvironment;

/**
 * Rede splits authorization from the API: the token is negotiated on one host
 * and spent on another, and the token path itself differs between sandbox and
 * production.
 */
trait HasRedeClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * boot client for the transactions API
     *
     * @return Client
     */
    protected function clientRedeBoot(): Client
    {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'content-type' => 'application/json',
                'accept'       => 'application/json',
                'user-agent'   => 'PHPay',
            ],
        ]);
    }

    /**
     * boot client for the authorization host
     *
     * @return Client
     */
    protected function clientRedeOauthBoot(): Client
    {
        return new Client([
            'base_uri' => $this->oauthUri(),
            'headers'  => [
                'accept'     => 'application/json',
                'user-agent' => 'PHPay',
            ],
        ]);
    }

    /**
     * base uri of the transactions API
     *
     * @return string
     */
    protected function baseUri(): string
    {
        return RedeEnvironment::api($this->sandbox);
    }

    /**
     * base uri of the authorization host
     *
     * @return string
     */
    protected function oauthUri(): string
    {
        return RedeEnvironment::oauth($this->sandbox);
    }

    /**
     * path of the token endpoint, which differs between environments
     *
     * @return string
     */
    protected function oauthTokenPath(): string
    {
        return RedeEnvironment::tokenPath($this->sandbox);
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
}
