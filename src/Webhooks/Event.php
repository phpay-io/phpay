<?php

namespace PHPay\Webhooks;

use DateTimeImmutable;
use PHPay\Support\PaymentStatus;

/**
 * one thing a gateway said happened.
 *
 * `webhookSignature()` proves the notification came from the gateway. This is
 * the other half: what it says. Nine gateways put the event type, the resource
 * id and the status in different places — and four of them put the status
 * nowhere at all.
 *
 * immutable, like `Money`, `Customer` and `StaticPixCode`. The raw payload is
 * carried untouched, because the translation covers the envelope and nothing
 * more: a gateway-specific field this version does not read is still there for
 * whoever needs it.
 */
final class Event
{
    /**
     * @param string $gateway name of the gateway that sent it
     * @param EventType $type the normalized type
     * @param string|null $rawType the gateway's own event name, when it sent one
     * @param string|null $resourceId id of the resource that changed, in that
     *                                gateway's own identifier
     * @param PaymentStatus|null $status null when the notification does not
     *                                   carry a state — see requiresLookup()
     * @param DateTimeImmutable|null $occurredAt when the gateway reports it
     * @param array<mixed> $payload the decoded body, untouched
     */
    public function __construct(
        public readonly string $gateway,
        public readonly EventType $type,
        public readonly ?string $rawType = null,
        public readonly ?string $resourceId = null,
        public readonly ?PaymentStatus $status = null,
        public readonly ?DateTimeImmutable $occurredAt = null,
        public readonly array $payload = [],
    ) {
    }

    /**
     * whether the state has to be fetched from the gateway.
     *
     * **`true` is not a failure.** Mercado Pago, Cielo, Rede and the Efí
     * Cobranças API notify that *something* changed on a resource and nothing
     * more; the state is only reachable by asking. Answering
     * `PaymentStatus::UNKNOWN` instead would read like a state that was
     * actually sent, which is worse than saying nothing:
     *
     * ```php
     * if ($evento->requiresLookup()) {
     *     $cobranca = $phpay->charge()->find($evento->resourceId);
     * }
     * ```
     *
     * @return bool
     */
    public function requiresLookup(): bool
    {
        return $this->status === null;
    }

    /**
     * whether the notification carried the state, with no second call needed.
     *
     * @return bool
     */
    public function hasStatus(): bool
    {
        return $this->status !== null;
    }

    /**
     * a field of the raw payload, by dotted path.
     *
     * for the gateway-specific data the envelope leaves out — the
     * `endToEndId` of an Efí Pix, the `pix.payer` of a Woovi charge — without
     * forcing a walk through nested arrays:
     *
     * ```php
     * $evento->get('pix.0.endToEndId');
     * $evento->get('payment.billingType', 'BOLETO');
     * ```
     *
     * @param string $path dotted path into the payload
     * @param mixed $default returned when the path does not exist
     * @return mixed
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->payload;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
