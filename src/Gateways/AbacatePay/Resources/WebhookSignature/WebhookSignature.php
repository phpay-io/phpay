<?php

namespace PHPay\AbacatePay\Resources\WebhookSignature;

use PHPay\AbacatePay\Resources\WebhookSignature\Interface\WebhookSignatureInterface;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\MasksCredentials;
use PHPay\Webhooks\{Headers, SignatureRequest};

/**
 * proves a notification came from AbacatePay.
 *
 * AbacatePay hands you two checks and recommends both. The one that always
 * works is the secret you yourself put in the webhook URL, which comes back
 * as the webhookSecret query parameter. On top of it, the request carries
 * X-Webhook-Signature — base64 of an HMAC-SHA256 over the raw body — and
 * that one is only checked when you set the key, because the documentation
 * calls it a public key while using it as an HMAC secret, so the value has
 * to come from your dashboard rather than from a guess of ours.
 *
 * @see https://docs.abacatepay.com/pages/webhooks/security.md
 */
class WebhookSignature implements WebhookSignatureInterface
{
    /**
     * keeps the secret out of dumps
     */
    use MasksCredentials;

    /**
     * name used in exception messages
     */
    private const GATEWAY = 'AbacatePay';

    /**
     * query parameter AbacatePay echoes back
     */
    private const PARAM = 'webhookSecret';

    /**
     * header with the body signature
     */
    private const HEADER = 'x-webhook-signature';

    /**
     * secret put in the webhook URL
     */
    private string $secret = '';

    /**
     * key the body is signed with, when checked
     */
    private string $signatureKey = '';

    /**
     * raw request body
     */
    private string $payload = '';

    /**
     * query string of the incoming request
     *
     * @var array<mixed>
     */
    private array $query = [];

    /**
     * headers of the incoming request
     *
     * @var array<mixed>
     */
    private array $headers = [];

    /**
     * secret you put in the webhook URL
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
     * query string of the incoming request
     *
     * @param array<mixed> $query
     * @return WebhookSignatureInterface
     */
    public function setQuery(array $query): WebhookSignatureInterface
    {
        $this->query = $query;

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
     * key AbacatePay signs the body with, when you also want that check
     *
     * @param string $signatureKey
     * @return WebhookSignatureInterface
     */
    public function setSignatureKey(#[\SensitiveParameter] string $signatureKey): WebhookSignatureInterface
    {
        $this->signatureKey = $signatureKey;

        return $this;
    }

    /**
     * whether the secret in the URL matches, and the body signature too
     * when a key was given
     *
     * @return bool
     * @throws ValidationException
     */
    public function verify(): bool
    {
        SignatureRequest::requireSecret(self::GATEWAY, $this->secret);

        $received = $this->query[self::PARAM] ?? null;

        if (!is_string($received) || !hash_equals($this->secret, $received)) {
            return false;
        }

        if ($this->signatureKey === '') {
            return true;
        }

        return $this->signedBody();
    }

    /**
     * whether the HMAC in the header matches the raw body
     *
     * @return bool
     * @throws ValidationException
     */
    private function signedBody(): bool
    {
        SignatureRequest::requirePayload(self::GATEWAY, $this->payload);

        $signature = Headers::find($this->headers, self::HEADER);

        if ($signature === null) {
            throw SignatureRequest::missingHeader(self::GATEWAY, self::HEADER);
        }

        $expected = base64_encode(hash_hmac('sha256', $this->payload, $this->signatureKey, true));

        return hash_equals($expected, $signature);
    }
}
