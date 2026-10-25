<?php

namespace PHPay\Inter\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Inter\Enums\RefundStatusEnum;
use PHPay\Support\PaymentStatus;
use PHPay\Webhooks\{Event, EventRequest, EventType};

/**
 * reads an Inter Pix notification.
 *
 * the Inter Pix API is the BACEN specification almost to the letter — same
 * paths, same schemas, same error taxonomy. **The notification is where it
 * diverges, twice, and both divergences are of the kind that fails silently.**
 *
 * **The body is a bare array.** Not `{"pix": [...]}` as the standard says and
 * as Efí sends: the array is the body. A parser written against the standard
 * envelope would find no `pix` key and answer with zero events for every real
 * payment — which is exactly why this capability is not declared on a guessed
 * shape. The `pix` key is still accepted, because the reference's own
 * "Evolução da API Pix" section reserves the right to add fields at any time,
 * and the cost of tolerating both is two lines.
 *
 * **`devolucoes` is a single object.** The standard and Efí both send an
 * array. This may well be an error in Inter's documentation, so both readings
 * are accepted: a list stays a list, an object becomes a list of one. Picking
 * one and being wrong would drop the return.
 *
 * there is no event name anywhere in the payload, the same as the Efí Pix API,
 * so the type comes from what the entry carries and not from a label. That is
 * not inference: an entry with a value, an `endToEndId` and a time of receipt
 * **is** the payment.
 */
final class InterWebhookEventRequest
{
    /**
     * @param string|array<mixed> $payload
     * @return array<int, Event>
     * @throws ValidationException
     */
    public static function parse(string|array $payload): array
    {
        $body = EventRequest::decode('Inter', $payload);

        $events = [];

        foreach (self::entries($body) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $events[] = self::received($entry, $body);

            foreach (self::refunds($entry) as $refund) {
                if (is_array($refund)) {
                    $events[] = self::refund($refund, $body);
                }
            }
        }

        if ($events !== []) {
            return $events;
        }

        /*
         * a shape this version does not recognize still arrived, and Inter
         * retries a notification only four times. Answering with an empty list
         * would read as "nothing happened"; one UNKNOWN event carrying the
         * payload lets the integration see it and decide.
         */
        return [new Event(
            gateway: 'Inter',
            type: EventType::UNKNOWN,
            payload: $body,
        )];
    }

    /**
     * the payment entries of the notification.
     *
     * @param array<mixed> $body
     * @return array<mixed>
     */
    private static function entries(array $body): array
    {
        if (array_is_list($body)) {
            return $body;
        }

        return is_array($body['pix'] ?? null) ? $body['pix'] : [];
    }

    /**
     * the returns of one entry, whether they came as a list or as one object.
     *
     * @param array<mixed> $entry
     * @return array<mixed>
     */
    private static function refunds(array $entry): array
    {
        $refunds = $entry['devolucoes'] ?? null;

        if (!is_array($refunds)) {
            return [];
        }

        /* a list of returns stays a list; the documented single object is wrapped */
        return array_is_list($refunds) ? $refunds : [$refunds];
    }

    /**
     * a Pix received.
     *
     * @param array<mixed> $entry
     * @param array<mixed> $body
     * @return Event
     */
    private static function received(array $entry, array $body): Event
    {
        return new Event(
            gateway: 'Inter',
            type: EventType::PAID,
            resourceId: EventRequest::id($entry['txid'] ?? null)
                ?? EventRequest::id($entry['endToEndId'] ?? null),
            status: PaymentStatus::PAID,
            occurredAt: EventRequest::date($entry['horario'] ?? null),
            payload: $body,
        );
    }

    /**
     * a return of that Pix.
     *
     * only `DEVOLVIDO` carries a state: a return still in processing, or one
     * that failed, is reported with none, so `requiresLookup()` says to ask
     * rather than letting anyone read a return in progress as one completed.
     *
     * the type is always REFUNDED and never PARTIALLY_REFUNDED. Telling the
     * two apart would mean comparing the returned amount with the charge's,
     * and the two amount fields in Inter's own example disagree in both type
     * and magnitude (`componentesValor.original.valor: 100` against
     * `valor: "110.00"`). Arithmetic on that would be a guess dressed as a
     * fact; the amounts stay readable in the payload.
     *
     * @param array<mixed> $refund
     * @param array<mixed> $body
     * @return Event
     */
    private static function refund(array $refund, array $body): Event
    {
        $raw     = is_string($refund['status'] ?? null) ? $refund['status'] : null;
        $horario = is_array($refund['horario'] ?? null) ? $refund['horario'] : [];

        return new Event(
            gateway: 'Inter',
            type: EventType::REFUNDED,
            rawType: $raw,
            resourceId: EventRequest::id($refund['id'] ?? null)
                ?? EventRequest::id($refund['rtrId'] ?? null),
            status: RefundStatusEnum::tryFrom($raw ?? '')?->isSettled() === true
                ? PaymentStatus::REFUNDED
                : null,
            occurredAt: EventRequest::date($horario['solicitacao'] ?? null),
            payload: $body,
        );
    }
}
