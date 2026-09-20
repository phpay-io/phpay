<?php

namespace PHPay\Exceptions;

use GuzzleHttp\Exception\BadResponseException;
use RuntimeException;
use Throwable;

/**
 * thrown when a gateway rejects a request or is unreachable.
 */
class ApiException extends RuntimeException implements PHPayException
{
    /**
     * construct
     *
     * @param string $message
     * @param string $gateway
     * @param int $statusCode http status, 0 when the request never reached the gateway
     * @param array<mixed> $response decoded response body
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        private string $gateway,
        private int $statusCode = 0,
        private array $response = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * build from the throwable raised by the http client.
     *
     * @param Throwable $exception
     * @param string $gateway
     * @param string $method
     * @param string $endpoint
     * @return self
     */
    public static function fromThrowable(
        Throwable $exception,
        string $gateway,
        string $method,
        string $endpoint
    ): self {
        $statusCode = 0;
        $body       = [];

        if ($exception instanceof BadResponseException) {
            $statusCode = $exception->getResponse()->getStatusCode();

            $decoded = json_decode(
                (string) $exception->getResponse()->getBody(),
                true
            );

            $body = is_array($decoded) ? $decoded : [];
        }

        $reason = self::summarize($body) ?? $exception->getMessage();

        $message = sprintf(
            '%s: %s %s falhou%s. %s',
            $gateway,
            strtoupper($method),
            $endpoint,
            $statusCode > 0 ? " com HTTP {$statusCode}" : '',
            $reason
        );

        return new self($message, $gateway, $statusCode, $body, $exception);
    }

    /**
     * gateway that produced the failure.
     *
     * @return string
     */
    public function getGateway(): string
    {
        return $this->gateway;
    }

    /**
     * http status code, 0 when the request never reached the gateway.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * decoded response body returned by the gateway.
     *
     * @return array<mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * the request never got an http response (dns, timeout, tls).
     *
     * @return bool
     */
    public function isConnectionError(): bool
    {
        return $this->statusCode === 0;
    }

    /**
     * extract a readable reason from an asaas or efí error payload.
     *
     * @param array<mixed> $body
     * @return string|null
     */
    private static function summarize(array $body): ?string
    {
        /* asaas: {"errors":[{"code":"...","description":"..."}]} */
        if (isset($body['errors']) && is_array($body['errors'])) {
            $descriptions = [];

            foreach ($body['errors'] as $error) {
                if (is_array($error) && isset($error['description']) && is_string($error['description'])) {
                    $descriptions[] = $error['description'];
                }
            }

            if (!empty($descriptions)) {
                return implode(' | ', $descriptions);
            }
        }

        /* efí: {"error":"...","error_description":"..."} */
        foreach (['error_description', 'error', 'message'] as $key) {
            if (isset($body[$key]) && is_string($body[$key])) {
                return $body[$key];
            }
        }

        return null;
    }
}
