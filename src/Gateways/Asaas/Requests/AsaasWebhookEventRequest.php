<?php

namespace PHPay\Asaas\Requests;

use PHPay\Asaas\Enums\InvoiceStatusEnum;
use PHPay\Asaas\Resources\Webhook\Enum\WebhookEventsEnum;
use PHPay\Exceptions\ValidationException;
use PHPay\Webhooks\{Event, EventRequest};

/**
 * reads an Asaas notification.
 *
 * the richest of the nine: the event name and the whole `payment` object come
 * in the body, so nothing has to be fetched back.
 */
final class AsaasWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body    = EventRequest::decode('Asaas', $payload);
        $event   = is_string($body['event'] ?? null) ? $body['event'] : null;
        $payment = is_array($body['payment'] ?? null) ? $body['payment'] : [];

        return [new Event(
            gateway: 'Asaas',
            type: WebhookEventsEnum::of($event),
            rawType: $event,
            resourceId: EventRequest::id($payment['id'] ?? null),
            status: InvoiceStatusEnum::of(is_string($payment['status'] ?? null) ? $payment['status'] : null),
            occurredAt: EventRequest::date($body['dateCreated'] ?? null),
            payload: $body,
        )];
    }
}
