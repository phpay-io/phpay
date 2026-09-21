<?php

namespace PHPay\PagarMe\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;

trait HasPagarMeClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * boot client
     *
     * Pagar.me authenticates with HTTP Basic: the secret key is the user and
     * the password is empty. Test and production share the same host — the key
     * prefix decides the environment.
     *
     * @return Client
     */
    protected function clientPagarMeBoot(): Client
    {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'content-type'  => 'application/json',
                'accept'        => 'application/json',
                'user-agent'    => 'PHPay',
                'Authorization' => 'Basic ' . base64_encode("{$this->secretKey}:"),
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
        return 'https://api.pagar.me/core/v5/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Pagar.me';
    }
}
