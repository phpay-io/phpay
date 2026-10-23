<?php

namespace PHPay\Efi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\PaymentStatus;
use PHPay\Webhooks\{Event, EventRequest, EventType};

/**
 * reads an Efí notification, from either of its two APIs.
 *
 * **the Pix API sends arrays**: `pix` holds every payment received in that
 * request, and a return notification holds the whole `devolucoes` array rather
 * than one per call. This is the gateway that forces the parsing to answer
 * with a list — collapsing it would lose payments silently.
 *
 * the Cobranças API sends something else entirely: a single `notification`
 * token, which is not the charge id and has to be exchanged for the charge's
 * detail. There, `requiresLookup()` is true and the token is the resource id.
 */
final class EfiWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body = EventRequest::decode('Efí', $payload);

        $events = array_merge(
            self::received(is_array($body['pix'] ?? null) ? $body['pix'] : [], $body),
            self::refunds(is_array($body['devolucoes'] ?? null) ? $body['devolucoes'] : [], $body),
        );

        if ($events !== []) {
            return $events;
        }

        /* Cobranças: a token to exchange, not a charge */
        $token = EventRequest::id($body['notification'] ?? null);

        return [new Event(
            gateway: 'Efí',
            type: EventType::UPDATED,
            resourceId: $token,
            status: null,
            payload: $body,
        )];
    }

    /**
     * every Pix received in this notification.
     *
     * the status is PAID because that is what the payload reports: a value, an
     * endToEndId and a time of receipt. It is not inferred from an event name —
     * the Pix API sends no event name at all.
     *
     * @param array<mixed> $pix
     * @param array<mixed> $body
     * @return array<int, Event>
     */
    private static function received(array $pix, array $body): array
    {
        $events = [];

        foreach ($pix as $received) {
            if (!is_array($received)) {
                continue;
            }

            $events[] = new Event(
                gateway: 'Efí',
                type: EventType::PAID,
                resourceId: EventRequest::id($received['txid'] ?? null)
                    ?? EventRequest::id($received['endToEndId'] ?? null),
                status: PaymentStatus::PAID,
                occurredAt: EventRequest::date($received['horario'] ?? null),
                payload: $body,
            );
        }

        return $events;
    }

    /**
     * every return in this notification.
     *
     * each one carries its own BACEN status (`EM_PROCESSAMENTO`, `DEVOLVIDO`,
     * `NAO_REALIZADO`), and only `DEVOLVIDO` means the money went back — the
     * other two are reported as the event with no state, so nobody reads a
     * return in progress as a return done.
     *
     * @param array<mixed> $devolucoes
     * @param array<mixed> $body
     * @return array<int, Event>
     */
    private static function refunds(array $devolucoes, array $body): array
    {
        $events = [];

        foreach ($devolucoes as $refund) {
            if (!is_array($refund)) {
                continue;
            }

            $raw     = is_string($refund['status'] ?? null) ? $refund['status'] : null;
            $horario = is_array($refund['horario'] ?? null) ? $refund['horario'] : [];

            $events[] = new Event(
                gateway: 'Efí',
                type: EventType::REFUNDED,
                rawType: $raw,
                resourceId: EventRequest::id($refund['id'] ?? null)
                    ?? EventRequest::id($refund['rtrId'] ?? null),
                status: $raw === 'DEVOLVIDO' ? PaymentStatus::REFUNDED : null,
                occurredAt: EventRequest::date($horario['solicitacao'] ?? null),
                payload: $body,
            );
        }

        return $events;
    }
}
