<?php

namespace PHPay\Cielo\Traits;

use GuzzleHttp\Client;
use PHPay\Exceptions\ApiException;
use PHPay\Http\HasHttpClient;

/**
 * Cielo splits its API across two hosts by operation type, not by domain:
 * writes go to the api host, reads to the query host. The same resource needs
 * both — create() on one, find() on the other.
 */
trait HasCieloClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * client used for queries
     */
    private Client $queryClient;

    /**
     * boot the client that takes writes
     *
     * @return Client
     */
    protected function clientCieloBoot(): Client
    {
        return $this->bootClient($this->baseUri());
    }

    /**
     * boot the client that takes queries
     *
     * @return Client
     */
    protected function clientCieloQueryBoot(): Client
    {
        return $this->bootClient($this->queryBaseUri());
    }

    /**
     * base uri for writes
     *
     * @return string
     */
    protected function baseUri(): string
    {
        return $this->sandbox
            ? 'https://apisandbox.cieloecommerce.cielo.com.br/'
            : 'https://api.cieloecommerce.cielo.com.br/';
    }

    /**
     * base uri for queries
     *
     * @return string
     */
    protected function queryBaseUri(): string
    {
        return $this->sandbox
            ? 'https://apiquerysandbox.cieloecommerce.cielo.com.br/'
            : 'https://apiquery.cieloecommerce.cielo.com.br/';
    }

    /**
     * read from the query host
     *
     * @param string $endpoint
     * @param array<mixed> $filters
     * @return array<mixed>
     * @throws ApiException
     */
    protected function queryGet(string $endpoint, array $filters = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $filters], $this->queryClient);
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Cielo';
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
                'content-type' => 'application/json',
                'accept'       => 'application/json',
                'user-agent'   => 'PHPay',
                'MerchantId'   => $this->merchantId,
                'MerchantKey'  => $this->merchantKey,
            ],
        ]);
    }
}
