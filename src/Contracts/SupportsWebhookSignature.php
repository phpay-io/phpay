<?php

namespace PHPay\Contracts;

/**
 * the gateway signs its webhooks in a way the receiver can verify.
 *
 * declared only where the gateway documents how: a notification that
 * carries no signature cannot be told apart from one forged by whoever
 * learns the URL, and a verifier that always answers true would be worse
 * than none.
 */
interface SupportsWebhookSignature extends GatewayInterface
{
    /**
     * get resource webhook signature from gateway.
     *
     * **stays `object`, deliberately.** Narrowing it to `VerifiesSignature`
     * would be compatible for callers but would break every *implementor*:
     * return types are covariant, so a private gateway declaring `: object`
     * would stop loading with a fatal error. Private gateways are a supported
     * scenario, and that is not a minor-version change.
     *
     * The facade narrows instead — `PHPay::webhookSignature()` answers with
     * the contract, and that is the path generic code takes. Typing against
     * this interface for dependency injection still gives `object`; closing
     * that gap needs a major.
     *
     * @return object a verifier, which the library's own gateways always
     *                implement as VerifiesSignature
     */
    public function webhookSignature(): object;
}
