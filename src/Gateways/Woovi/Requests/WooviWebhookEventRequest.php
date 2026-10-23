<?php

namespace PHPay\Woovi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Webhooks\{Event, EventRequest};
use PHPay\Woovi\Enums\{ChargeStatusEnum, WebhookEventsEnum};

/**
 * reads a Woovi/OpenPix notification.
 *
 * the body carries `event` plus `charge` and `pix` — or `boleto`, on the
 * boleto webhook. The charge is addressed by the integrator's own
 * `correlationID`, which is also what `find()` accepts.
 */
final class WooviWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body   = EventRequest::decode('Woovi', $payload);
        $event  = is_string($body['event'] ?? null) ? $body['event'] : null;
        $charge = is_array($body['charge'] ?? null) ? $body['charge'] : [];

        return [new Event(
            gateway: 'Woovi',
            type: WebhookEventsEnum::of($event),
            rawType: $event,
            resourceId: EventRequest::id($charge['correlationID'] ?? null)
                ?? EventRequest::id($charge['identifier'] ?? null),
            status: ChargeStatusEnum::of(is_string($charge['status'] ?? null) ? $charge['status'] : null),
            occurredAt: EventRequest::date($charge['paidAt'] ?? $charge['createdAt'] ?? null),
            payload: $body,
        )];
    }
}
