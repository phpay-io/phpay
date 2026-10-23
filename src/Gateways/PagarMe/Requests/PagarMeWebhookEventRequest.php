<?php

namespace PHPay\PagarMe\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\PagarMe\Enums\{ChargeStatusEnum, WebhookEventsEnum};
use PHPay\Webhooks\{Event, EventRequest};

/**
 * reads a Pagar.me v5 notification.
 *
 * the body carries `type` and `data`, where `data` is the order or the charge
 * depending on which level fired. On an `order.*` event the status read is the
 * order's, which is a shorter set than the charge's.
 */
final class PagarMeWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body = EventRequest::decode('Pagar.me', $payload);
        $type = is_string($body['type'] ?? null) ? $body['type'] : null;
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return [new Event(
            gateway: 'Pagar.me',
            type: WebhookEventsEnum::of($type),
            rawType: $type,
            resourceId: EventRequest::id($data['id'] ?? null),
            status: ChargeStatusEnum::of(is_string($data['status'] ?? null) ? $data['status'] : null),
            occurredAt: EventRequest::date($body['created_at'] ?? $data['updated_at'] ?? null),
            payload: $body,
        )];
    }
}
