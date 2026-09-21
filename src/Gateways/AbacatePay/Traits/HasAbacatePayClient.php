<?php

namespace PHPay\AbacatePay\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;

trait HasAbacatePayClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * boot client
     *
     * AbacatePay serves one host for both environments. Which one answers is
     * decided by the key you send — a dev mode key or a production one — and
     * the key carries no prefix that tells them apart, so the library does not
     * guess. The `devMode` field of a billing response is the honest answer.
     *
     * @return Client
     */
    protected function clientAbacatePayBoot(): Client
    {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'content-type'  => 'application/json',
                'accept'        => 'application/json',
                'user-agent'    => 'PHPay',
                'Authorization' => "Bearer {$this->token}",
            ],
        ]);
    }

    /**
     * base uri
     *
     * @return string
     */
    protected function baseUri(): string
    {
        return 'https://api.abacatepay.com/v1/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'AbacatePay';
    }
}
