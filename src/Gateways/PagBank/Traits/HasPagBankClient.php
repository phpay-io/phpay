<?php

namespace PHPay\PagBank\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;

/**
 * PagBank splits its surface across two APIs on different hosts: orders and
 * charges live on api.pagseguro.com, while plans, subscribers and
 * subscriptions live on api.assinaturas.pagseguro.com. A resource boots the
 * client of the API it belongs to.
 */
trait HasPagBankClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * boot client for the orders API
     *
     * @return Client
     */
    protected function clientPagBankBoot(): Client
    {
        return $this->bootClient($this->baseUri());
    }

    /**
     * boot client for the subscriptions API
     *
     * @return Client
     */
    protected function clientPagBankSubscriptionsBoot(): Client
    {
        return $this->bootClient($this->subscriptionsBaseUri());
    }

    /**
     * base uri of the orders API
     *
     * @return string
     */
    protected function baseUri(): string
    {
        return $this->sandbox
            ? 'https://sandbox.api.pagseguro.com/'
            : 'https://api.pagseguro.com/';
    }

    /**
     * base uri of the subscriptions API
     *
     * @return string
     */
    protected function subscriptionsBaseUri(): string
    {
        return $this->sandbox
            ? 'https://sandbox.api.assinaturas.pagseguro.com/'
            : 'https://api.assinaturas.pagseguro.com/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'PagBank';
    }

    /**
     * build a client for the given host
     *
     * @param string $baseUri
     * @return Client
     */
    private function bootClient(string $baseUri): Client
    {
        return new Client([
            'base_uri' => $baseUri,
            'headers'  => [
                'content-type'  => 'application/json',
                'accept'        => 'application/json',
                'user-agent'    => 'PHPay',
                'Authorization' => "Bearer {$this->token}",
            ],
        ]);
    }
}
