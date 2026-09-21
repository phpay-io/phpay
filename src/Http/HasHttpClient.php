<?php

namespace PHPay\Http;

use GuzzleHttp\Client;
use PHPay\Exceptions\ApiException;
use Throwable;

/**
 * shared http verbs for every gateway.
 *
 * the using class must expose a `GuzzleHttp\Client $client` property.
 * every failure is surfaced as an ApiException — a returned array is always
 * a successful response body.
 */
trait HasHttpClient
{
    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    abstract protected function gatewayName(): string;

    /**
     * get data
     *
     * @param string $endpoint
     * @param array<mixed> $filters
     * @return array<mixed>
     * @throws ApiException
     */
    protected function get(string $endpoint, array $filters = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $filters]);
    }

    /**
     * post data
     *
     * @param string $endpoint
     * @param array<mixed> $data
     * @param array<string, string> $headers extra headers for this request only
     * @return array<mixed>
     * @throws ApiException
     */
    protected function post(string $endpoint, array $data = [], array $headers = []): array
    {
        $options = ['json' => $data];

        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        return $this->request('POST', $endpoint, $options);
    }

    /**
     * put data
     *
     * @param string $endpoint
     * @param array<mixed> $data
     * @param array<string, string> $headers extra headers for this request only
     * @return array<mixed>
     * @throws ApiException
     */
    protected function put(string $endpoint, array $data = [], array $headers = []): array
    {
        $options = ['json' => $data];

        if (!empty($headers)) {
            $options['headers'] = $headers;
        }

        return $this->request('PUT', $endpoint, $options);
    }

    /**
     * patch data
     *
     * @param string $endpoint
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws ApiException
     */
    protected function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, ['json' => $data]);
    }

    /**
     * delete data
     *
     * @param string $endpoint
     * @return bool true when the gateway accepted the deletion
     * @throws ApiException
     */
    protected function delete(string $endpoint): bool
    {
        $this->request('DELETE', $endpoint);

        return true;
    }

    /**
     * perform the request and decode the response body.
     *
     * @param string $method
     * @param string $endpoint
     * @param array<mixed> $options
     * @param Client|null $client overrides $this->client — for gateways that
     *                            split operations across more than one host
     * @return array<mixed>
     * @throws ApiException
     */
    protected function request(
        string $method,
        string $endpoint,
        array $options = [],
        ?Client $client = null
    ): array {
        try {
            $response = ($client ?? $this->client)->request($method, $endpoint, $options);
        } catch (Throwable $exception) {
            throw ApiException::fromThrowable(
                $exception,
                $this->gatewayName(),
                $method,
                $endpoint
            );
        }

        $content = $response->getBody()->getContents();

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
