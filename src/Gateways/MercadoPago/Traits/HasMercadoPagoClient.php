<?php

namespace PHPay\MercadoPago\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;

trait HasMercadoPagoClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * boot client
     *
     * unlike Asaas and Efí, Mercado Pago has a single host: the environment is
     * decided by the access token itself, so there is no sandbox base uri.
     *
     * @return Client
     */
    protected function clientMercadoPagoBoot(): Client
    {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'content-type'  => 'application/json',
                'accept'        => 'application/json',
                'user-agent'    => 'PHPay',
                'Authorization' => "Bearer {$this->accessToken}",
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
        return 'https://api.mercadopago.com/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Mercado Pago';
    }

    /**
     * generate an idempotency key for a write that must not be duplicated.
     *
     * @return string
     */
    protected function idempotencyKey(): string
    {
        return bin2hex(random_bytes(16));
    }
}
