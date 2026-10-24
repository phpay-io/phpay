<?php

namespace PHPay\Webhooks;

use PHPay\Exceptions\ValidationException;

/**
 * what every webhook verifier can do, whatever the scheme.
 *
 * the five gateways that sign agree on exactly this much — a secret, the raw
 * body, the headers, and an answer — and disagree on everything else: token in
 * a header, HMAC of the body, RSA against a published key, a secret in the
 * query. That shared part is what generic code needs, and before this contract
 * existed it could not reach it: `webhookSignature()` returned `object`, so a
 * framework bridge or any middleware that resolves the gateway by name had
 * nothing to type against.
 *
 * holding the concrete gateway never had the problem — `AsaasGatewayInterface`
 * already returns the concrete verifier. This is for whoever cannot hold the
 * concrete gateway, which is the case `PHPay::make()` exists to serve.
 *
 * the scheme-specific setters stay where they are: `setDataId()` on Mercado
 * Pago, `setQuery()` and `setSignatureKey()` on AbacatePay, `setPublicKeys()`
 * on Woovi. They belong to one scheme each, not to the contract, and whoever
 * needs them asks with `method_exists()`.
 */
interface VerifiesSignature
{
    /**
     * secret the webhook was registered with.
     *
     * @param string $secret
     * @return VerifiesSignature
     */
    public function setSecret(string $secret): VerifiesSignature;

    /**
     * raw request body, exactly as it arrived.
     *
     * reserializing the decoded JSON changes the hash, so this has to be the
     * body as it came off the wire — `php://input`, not `json_encode()` of the
     * array.
     *
     * @param string $payload
     * @return VerifiesSignature
     */
    public function setPayload(string $payload): VerifiesSignature;

    /**
     * headers of the request, in any of the usual shapes.
     *
     * the name is looked up case-insensitively and through the `HTTP_` prefix,
     * so `getallheaders()`, `$_SERVER` and a PSR-7 header list all work.
     *
     * @param array<mixed> $headers
     * @return VerifiesSignature
     */
    public function setHeaders(array $headers): VerifiesSignature;

    /**
     * whether the notification really came from the gateway.
     *
     * **returns `bool`, and that is the point**: a signature that does not
     * match is an answer, not a failure of the library. What raises is wrong
     * use — no secret, an empty body, or a signature header that never
     * arrived.
     *
     * @return bool
     * @throws ValidationException
     */
    public function verify(): bool;
}
