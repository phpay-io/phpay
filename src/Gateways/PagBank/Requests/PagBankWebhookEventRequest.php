<?php

namespace PHPay\PagBank\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\PagBank\Enums\ChargeStatusEnum;
use PHPay\Support\PaymentStatus;
use PHPay\Webhooks\{Event, EventRequest, EventType};

/**
 * reads a PagBank notification.
 *
 * PagBank posts the **whole Order** and **no event field**, so the type is
 * inferred from each charge's status — the only thing in the body that says
 * what happened.
 *
 * one event per charge: an order can hold more than one, and collapsing them
 * into a single event would hide a charge that failed next to one that paid.
 * An order that arrives with no charges yet is one UPDATED event for the order
 * itself.
 */
final class PagBankWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body    = EventRequest::decode('PagBank', $payload);
        $charges = is_array($body['charges'] ?? null) ? $body['charges'] : [];

        $events = [];

        foreach ($charges as $charge) {
            if (!is_array($charge)) {
                continue;
            }

            $raw    = is_string($charge['status'] ?? null) ? $charge['status'] : null;
            $status = ChargeStatusEnum::of($raw);

            $events[] = new Event(
                gateway: 'PagBank',
                type: self::typeOf($status),
                rawType: $raw,
                resourceId: EventRequest::id($charge['id'] ?? null),
                status: $status,
                occurredAt: EventRequest::date($charge['paid_at'] ?? $charge['created_at'] ?? null),
                payload: $body,
            );
        }

        if ($events === []) {
            $events[] = new Event(
                gateway: 'PagBank',
                type: EventType::UPDATED,
                resourceId: EventRequest::id($body['id'] ?? null),
                occurredAt: EventRequest::date($body['created_at'] ?? null),
                payload: $body,
            );
        }

        return $events;
    }

    /**
     * the event a charge's status implies.
     *
     * inference, and the only one available: PagBank says what the charge *is*
     * and never what just happened to it.
     *
     * @param PaymentStatus $status
     * @return EventType
     */
    private static function typeOf(PaymentStatus $status): EventType
    {
        return match ($status) {
            PaymentStatus::PAID       => EventType::PAID,
            PaymentStatus::AUTHORIZED => EventType::AUTHORIZED,
            PaymentStatus::CANCELED   => EventType::CANCELED,
            PaymentStatus::FAILED     => EventType::FAILED,
            PaymentStatus::REFUNDED   => EventType::REFUNDED,
            default                   => EventType::UPDATED,
        };
    }
}
