<?php

namespace PHPay\Asaas\Resources\WebhookSignature;

use PHPay\Asaas\Resources\WebhookSignature\Interface\WebhookSignatureInterface;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\MasksCredentials;
use PHPay\Webhooks\{Headers, SignatureRequest};

/**
 * proves a notification came from Asaas.
 *
 * Asaas sends the token configured on the webhook in every request, in the
 * asaas-access-token header, and that is the whole scheme: the body is not
 * signed. So this proves the sender knew the token — not that the body
 * arrived unchanged, and not that the request is recent. It is why Asaas
 * asks for a token of 32 to 255 characters and tells you never to reuse the
 * API key here.
 *
 * @see https://docs.asaas.com/docs/about-webhooks
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
    private const GATEWAY = 'Asaas';

    /**
     * header Asaas puts the token in
     */
    private const HEADER = 'asaas-access-token';

    /**
     * token configured on the webhook
     */
    private string $secret = '';

    /**
     * headers of the incoming request
     *
     * @var array<mixed>
     */
    private array $headers = [];

    /**
     * token configured on the webhook
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
     * accepted for a uniform API: Asaas does not sign the body.
     *
     * @param string $payload
     * @return WebhookSignatureInterface
     */
    public function setPayload(string $payload): WebhookSignatureInterface
    {
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
     * whether the token in the request matches the configured one
     *
     * @return bool
     * @throws ValidationException
     */
    public function verify(): bool
    {
        SignatureRequest::requireSecret(self::GATEWAY, $this->secret);

        $received = Headers::find($this->headers, self::HEADER);

        if ($received === null) {
            throw SignatureRequest::missingHeader(self::GATEWAY, self::HEADER);
        }

        return hash_equals($this->secret, $received);
    }
}
