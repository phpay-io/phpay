<?php

namespace PHPay\MercadoPago\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Webhooks\{Event, EventRequest, EventType};

/**
 * reads a Mercado Pago notification.
 *
 * the body carries `type`, `action` and a `data` holding **only the resource
 * id** — no status, no amount. So `status()` is null and `requiresLookup()` is
 * true, and the id is what `charge()->find()` takes.
 *
 * `data.id` comes as a number in Mercado Pago's own examples and as a string
 * elsewhere, which is why it is narrowed rather than cast.
 */
final class MercadoPagoWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body   = EventRequest::decode('Mercado Pago', $payload);
        $action = is_string($body['action'] ?? null) ? $body['action'] : null;
        $data   = is_array($body['data'] ?? null) ? $body['data'] : [];

        return [new Event(
            gateway: 'Mercado Pago',
            type: self::typeOf($action),
            rawType: $action ?? (is_string($body['type'] ?? null) ? $body['type'] : null),
            resourceId: EventRequest::id($data['id'] ?? null),
            status: null,
            occurredAt: EventRequest::date($body['date_created'] ?? null),
            payload: $body,
        )];
    }

    /**
     * the event an `action` reports.
     *
     * the documented values are the `.created` and `.updated` pair, and the
     * docs describe the field as saying whether the notification is a creation
     * or an update — so the suffix is what carries the meaning, across the
     * resource prefixes (`payment`, `subscription_preapproval`, …).
     *
     * @param string|null $action
     * @return EventType
     */
    private static function typeOf(?string $action): EventType
    {
        if ($action === null) {
            return EventType::UNKNOWN;
        }

        return match (true) {
            str_ends_with($action, '.created') => EventType::CREATED,
            str_ends_with($action, '.updated') => EventType::UPDATED,
            default                            => EventType::UNKNOWN,
        };
    }
}
