<?php

namespace PHPay\Woovi\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;

trait HasWooviClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * boot client
     *
     * the AppID goes in Authorization with no scheme — not Bearer, not Basic.
     *
     * @return Client
     */
    protected function clientWooviBoot(): Client
    {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'content-type'  => 'application/json',
                'accept'        => 'application/json',
                'user-agent'    => 'PHPay',
                'Authorization' => $this->appId,
            ],
        ]);
    }

    /**
     * base uri
     *
     * sandbox lives on a domain of its own, not on a path or subdomain of
     * production.
     *
     * @return string
     */
    protected function baseUri(): string
    {
        return $this->sandbox
            ? 'https://api.woovi-sandbox.com/'
            : 'https://api.openpix.com.br/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Woovi';
    }
}
