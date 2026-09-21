<?php

namespace PHPay\Asaas\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;

trait HasAsaasClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * base uri for the current environment
     *
     * declared as a method, not a constant: constants inside traits only
     * exist from PHP 8.2 and this library supports 8.1.
     *
     * @return string
     */
    protected function baseUri(): string
    {
        return $this->sandbox
            ? 'https://sandbox.asaas.com/api/v3/'
            : 'https://www.asaas.com/api/v3/';
    }

    /**
     * boot client
     *
     * @return Client
     */
    protected function clientAsaasBoot(): Client
    {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'content-type' => 'application/json',
                'user-agent'   => 'PHPay',
                'access_token' => $this->token,
            ],
        ]);
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Asaas';
    }
}
