<?php

namespace PHPay\MercadoPago\Resources\WebhookSignature;

use PHPay\Exceptions\ValidationException;
use PHPay\Http\MasksCredentials;
use PHPay\MercadoPago\Resources\WebhookSignature\Interface\WebhookSignatureInterface;
use PHPay\Webhooks\{Headers, SignatureRequest};

/**
 * proves a notification came from Mercado Pago.
 *
 * the x-signature header carries a timestamp and a hash, as ts=...,v1=...,
 * and the hash is an HMAC-SHA256 over a manifest built from three values the
 * request itself brings: data.id from the query string, the x-request-id
 * header and that same ts. A segment whose value is missing is left out of
 * the manifest instead of being sent empty — the official SDK does the same,
 * and a verifier that always sent all three would reject every notification
 * that omits one.
 *
 * The body is NOT part of the manifest. This proves the notification came
 * from Mercado Pago and refers to that resource; it does not prove the body
 * is untouched, so keep fetching the resource before acting on it.
 *
 * @see https://github.com/mercadopago/sdk-php/blob/master/src/MercadoPago/Webhook/WebhookSignatureValidator.php
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
    private const GATEWAY = 'Mercado Pago';

    /**
     * header with the timestamp and the hash
     */
    private const HEADER = 'x-signature';

    /**
     * header whose value goes into the manifest
     */
    private const REQUEST_ID_HEADER = 'x-request-id';

    /**
     * secret signature of the application
     */
    private string $secret = '';

    /**
     * headers of the incoming request
     *
     * @var array<mixed>
     */
    private array $headers = [];

    /**
     * data.id from the query string
     */
    private ?string $dataId = null;

    /**
     * secret signature generated in Your Integrations
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
     * accepted for a uniform API: the manifest does not include the body.
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
     * data.id from the query string of the notification
     *
     * @param string|null $dataId
     * @return WebhookSignatureInterface
     */
    public function setDataId(?string $dataId): WebhookSignatureInterface
    {
        $this->dataId = $dataId;

        return $this;
    }

    /**
     * whether the hash in the header matches the manifest
     *
     * @return bool
     * @throws ValidationException
     */
    public function verify(): bool
    {
        SignatureRequest::requireSecret(self::GATEWAY, $this->secret);

        $header = Headers::find($this->headers, self::HEADER);

        if ($header === null) {
            throw SignatureRequest::missingHeader(self::GATEWAY, self::HEADER);
        }

        $parts = $this->parse($header);

        if ($parts['ts'] === null || $parts['v1'] === null) {
            throw ValidationException::make(self::GATEWAY, $this->messages()->malformed);
        }

        $computed = hash_hmac('sha256', $this->manifest($parts['ts']), $this->secret);

        return hash_equals($computed, $parts['v1']);
    }

    /**
     * ts and v1 out of the header.
     *
     * @param string $header
     * @return array{ts: string|null, v1: string|null}
     */
    private function parse(string $header): array
    {
        $found = ['ts' => null, 'v1' => null];

        foreach (explode(',', $header) as $pair) {
            $pieces = explode('=', $pair, 2);

            if (count($pieces) !== 2) {
                continue;
            }

            $key   = strtolower(trim($pieces[0]));
            $value = trim($pieces[1]);

            if ($key === 'ts' && ctype_digit($value)) {
                $found['ts'] = $value;
            }

            if ($key === 'v1' && $value !== '') {
                $found['v1'] = $value;
            }
        }

        return $found;
    }

    /**
     * the string Mercado Pago signs, with the absent segments left out.
     *
     * @param string $ts
     * @return string
     */
    private function manifest(string $ts): string
    {
        $parts = [];

        if ($this->dataId !== null && $this->dataId !== '') {
            $parts[] = 'id:' . $this->dataId;
        }

        $requestId = Headers::find($this->headers, self::REQUEST_ID_HEADER);

        if ($requestId !== null && $requestId !== '') {
            $parts[] = 'request-id:' . $requestId;
        }

        $parts[] = 'ts:' . $ts;

        return implode(';', $parts) . ';';
    }

    /**
     * messages of this resource.
     *
     * @return object{malformed: string}
     */
    private function messages(): object
    {
        return (object) [
            'malformed' => 'O header x-signature não traz ts e v1 no formato esperado (ts=<inteiro>,v1=<hash>).',
        ];
    }
}
