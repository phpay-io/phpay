<?php

namespace PHPay\Contracts;

use PHPay\Exceptions\ValidationException;
use PHPay\Webhooks\Event;

/**
 * the library knows how to read this gateway's notification.
 *
 * the other half of `SupportsWebhookSignature`: that one proves the
 * notification came from the gateway, this one says what it means. They are
 * separate because they fail separately — a gateway can sign without the
 * library knowing its payload, and the reverse.
 *
 * declared only where the notification format is documented and confirmed. A
 * parser built on a guessed shape would answer UNKNOWN for every real event,
 * or worse, read the wrong field as an id.
 *
 * like `SupportsRefunds`, this capability declares an operation instead of a
 * resource getter: what diverges between the gateways is the reading, not a
 * handle to reach.
 */
interface SupportsWebhookEvents extends GatewayInterface
{
    /**
     * read a notification into events.
     *
     * **returns a list, never a single event.** The Efí Pix API sends `pix` as
     * an array — several payments received in one request — and returns as
     * another whole array, so a notification is not one event. Treating it as
     * one loses payments silently.
     *
     * verify the signature first, with the raw body, and only then parse: a
     * payload that was not proved to come from the gateway should not be read
     * at all.
     *
     * @param string|array<mixed> $payload the raw body, or it already decoded
     * @return array<int, Event>
     * @throws ValidationException when the body is empty or is not JSON
     */
    public function webhookEvents(string|array $payload): array;
}
