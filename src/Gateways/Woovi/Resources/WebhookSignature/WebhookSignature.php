<?php

namespace PHPay\Woovi\Resources\WebhookSignature;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Webhooks\{Headers, SignatureRequest};
use PHPay\Woovi\Resources\WebhookSignature\Interface\WebhookSignatureInterface;
use PHPay\Woovi\Traits\HasWooviClient;

/**
 * proves a notification came from Woovi/OpenPix.
 *
 * Woovi signs the raw body with its own private key and sends the signature,
 * base64 of an RSA-SHA256, in x-webhook-signature. The matching public key is
 * the same for every webhook and is served, without authentication, by
 * api/v1/webhook/public-keys. The endpoint answers a list because keys are
 * rotated: a notification signed with the previous key is still in flight
 * while the new one is already published, so every key in the list is
 * accepted — verifying only against the current one would drop good events
 * during a rotation.
 *
 * The older scheme, an HMAC-SHA1 in x-openpix-signature keyed with the
 * webhook secret, is deprecated by Woovi and only tried when a secret is set
 * and no RSA signature came.
 *
 * @see https://developers.woovi.com/en/docs/webhook/seguranca/webhook-signature-validation
 */
class WebhookSignature implements WebhookSignatureInterface
{
    /**
     * trait woovi client
     */
    use HasWooviClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * name used in exception messages
     */
    private const GATEWAY = 'Woovi';

    /**
     * header with the RSA signature
     */
    private const HEADER = 'x-webhook-signature';

    /**
     * header of the deprecated HMAC scheme
     */
    private const HMAC_HEADER = 'x-openpix-signature';

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
     * HMAC secret of the webhook, deprecated scheme
     */
    private string $secret = '';

    /**
     * public keys given instead of fetched
     *
     * @var array<int, string>
     */
    private array $publicKeys = [];

    /**
     * construct
     *
     * @param string $appId
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $appId,
        private bool $sandbox = true,
        ?Client $client = null
    ) {
        $this->client = $client ?? $this->clientWooviBoot();
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
     * HMAC secret of the webhook, for the deprecated scheme
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
     * public keys to verify against, instead of fetching them
     *
     * @param array<int, string> $publicKeys PEM encoded
     * @return WebhookSignatureInterface
     */
    public function setPublicKeys(array $publicKeys): WebhookSignatureInterface
    {
        $this->publicKeys = $publicKeys;

        return $this;
    }

    /**
     * whether the signature matches the body
     *
     * @return bool
     * @throws ApiException
     * @throws ValidationException
     */
    public function verify(): bool
    {
        SignatureRequest::requirePayload(self::GATEWAY, $this->payload);

        $signature = Headers::find($this->headers, self::HEADER);

        if ($signature === null) {
            return $this->verifyHmac();
        }

        $raw = base64_decode($signature, true);

        if ($raw === false || $raw === '') {
            return false;
        }

        foreach ($this->keys() as $key) {
            if (openssl_verify($this->payload, $raw, $key, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * the deprecated HMAC-SHA1 scheme, only when a secret was set.
     *
     * @return bool
     * @throws ValidationException
     */
    private function verifyHmac(): bool
    {
        if ($this->secret === '') {
            throw SignatureRequest::missingHeader(self::GATEWAY, self::HEADER);
        }

        $signature = Headers::find($this->headers, self::HMAC_HEADER);

        if ($signature === null) {
            throw SignatureRequest::missingHeader(self::GATEWAY, self::HMAC_HEADER);
        }

        $expected = base64_encode(hash_hmac('sha1', $this->payload, $this->secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * the keys to verify against: the ones given, or the published list.
     *
     * @return array<int, string>
     * @throws ApiException
     */
    private function keys(): array
    {
        if ($this->publicKeys !== []) {
            return $this->publicKeys;
        }

        $response = $this->get('api/v1/webhook/public-keys');
        $keys     = [];

        $published = $response['public_keys'] ?? [];

        if (is_array($published)) {
            foreach ($published as $entry) {
                $key = is_array($entry) ? $entry['key'] ?? null : null;

                if (is_string($key) && $key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }
}
