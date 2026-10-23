<?php

namespace PHPay\Cielo\Requests;

use PHPay\Cielo\Enums\NotificationChangeTypeEnum;
use PHPay\Exceptions\ValidationException;
use PHPay\Webhooks\{Event, EventRequest};

/**
 * reads a Cielo notification post.
 *
 * three fields and no state: `status()` is always null here and
 * `requiresLookup()` always true, which is not a shortcoming of the parsing —
 * it is what Cielo sends, and the documentation's own instruction is to query
 * the transaction by the `PaymentId`.
 */
final class CieloWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body = EventRequest::decode('Cielo', $payload);

        /* RecurrentPaymentId only comes on types 2 and 4, and addresses the
           recurrence rather than the sale */
        $id = EventRequest::id($body['PaymentId'] ?? null)
            ?? EventRequest::id($body['RecurrentPaymentId'] ?? null);

        $change = $body['ChangeType'] ?? null;

        return [new Event(
            gateway: 'Cielo',
            type: NotificationChangeTypeEnum::of($change),
            rawType: is_scalar($change) ? (string) $change : null,
            resourceId: $id,
            status: null,
            payload: $body,
        )];
    }
}
