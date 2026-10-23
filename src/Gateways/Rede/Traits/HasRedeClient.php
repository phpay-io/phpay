<?php

namespace PHPay\Rede\Traits;

use GuzzleHttp\Client;
use PHPay\Http\{ClientFactory, HasHttpClient};
use PHPay\Rede\RedeEnvironment;

/**
 * client of the Rede transactions API.
 *
 * only the transactions host lives here. Rede negotiates the token on a
 * separate host, with a path that differs between sandbox and production, but
 * that belongs to `RedeGateway::authorization()` — the resource that uses this
 * trait spends the token and never asks for one.
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
        return ClientFactory::make([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'content-type' => 'application/json',
                'accept'       => 'application/json',
                'user-agent'   => 'PHPay',
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
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Rede';
    }
}
