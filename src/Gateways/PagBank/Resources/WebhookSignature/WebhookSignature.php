<?php

namespace PHPay\PagBank\Resources\WebhookSignature;

use PHPay\Exceptions\ValidationException;
use PHPay\Http\MasksCredentials;
use PHPay\PagBank\Resources\WebhookSignature\Interface\WebhookSignatureInterface;
use PHPay\Webhooks\{Headers, SignatureRequest};

/**
 * proves a notification came from PagBank.
 *
 * PagBank hashes the account token and the raw body together, with a hyphen
 * between them, and sends the hex digest in the x-authenticity-token header.
 * The body is part of the hash, so a changed body fails — which also means
 * the body must be the raw one: re-encoding the JSON changes a space and the
 * hash no longer matches.
 *
 * @see https://developer.pagbank.com.br/reference/confirmar-autenticidade-da-notificacao
 */
class WebhookSignature implements WebhookSignatureInterface
{
    /**
     * keeps the token out of dumps
     */
    use MasksCredentials;

    /**
     * name used in exception messages
     */
    private const GATEWAY = 'PagBank';

    /**
     * header PagBank puts the digest in
     */
    private const HEADER = 'x-authenticity-token';

    /**
     * account token, from iBanking
     */
    private string $secret = '';

    /**
     * raw request body
     */
    private string $payload = '';

    /**
     * headers of the incoming request
     *
     * @var array<mixed>
     */
    private array $headers = [];

    /**
     * account token the notification is signed with
     *
     * @param string $secret
     * @return WebhookSignatureInterface
     */
    public function setSecret(#[\SensitiveParameter] string $secret): WebhookSignatureInterface
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * raw request body, exactly as it arrived
     *
     * @param string $payload
     * @return WebhookSignatureInterface
     */
    public function setPayload(string $payload): WebhookSignatureInterface
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * headers of the request, in any of the usual shapes
     *
     * @param array<mixed> $headers
     * @return WebhookSignatureInterface
     */
    public function setHeaders(array $headers): WebhookSignatureInterface
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * whether the digest in the request matches the body and the token
     *
     * @return bool
     * @throws ValidationException
     */
    public function verify(): bool
    {
        SignatureRequest::validate(self::GATEWAY, $this->payload, $this->secret);

        $received = Headers::find($this->headers, self::HEADER);

        if ($received === null) {
            throw SignatureRequest::missingHeader(self::GATEWAY, self::HEADER);
        }

        $expected = hash('sha256', $this->secret . '-' . $this->payload);

        return hash_equals($expected, strtolower($received));
    }
}
