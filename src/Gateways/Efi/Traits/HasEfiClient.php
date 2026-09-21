<?php

namespace PHPay\Efi\Traits;

use GuzzleHttp\Client;
use PHPay\Http\HasHttpClient;

trait HasEfiClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * client used to exchange credentials for an access token
     *
     * @param string $clientId
     * @param string $clientSecret
     * @return Client
     */
    protected function clientEfiAuthorize(
        string $clientId,
        string $clientSecret
    ): Client {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'Authorization' => 'Basic ' . base64_encode("{$clientId}:{$clientSecret}"),
                'content-type'  => 'application/json',
            ],
        ]);
    }

    /**
     * boot client
     *
     * @param string $token
     * @param string $type
     * @return Client
     */
    protected function clientEfiBoot(string $token, string $type): Client
    {
        return new Client([
            'base_uri' => $this->baseUri(),
            'headers'  => [
                'Authorization' => "{$type} {$token}",
                'content-type'  => 'application/json',
            ],
        ]);
    }

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
            ? 'https://cobrancas-h.api.efipay.com.br/'
            : 'https://cobrancas.api.efipay.com.br/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Efí';
    }
}
