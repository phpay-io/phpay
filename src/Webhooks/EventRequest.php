<?php

namespace PHPay\Webhooks;

use DateTimeImmutable;
use Exception;
use PHPay\Exceptions\ValidationException;

/**
 * what reading a notification needs before anything is read.
 *
 * same reasoning as `SignatureRequest`: a parser handed an empty body would
 * answer with an empty list, which reads exactly like a notification that
 * carried no events. That is a mistake in the integration, not a quiet case,
 * so it gets an exception.
 */
final class EventRequest
{
    /**
     * the body, decoded and checked.
     *
     * accepts the raw string, which is what `php://input` gives, or an array
     * already decoded. Verify the signature with the **raw** body first — this
     * decode is for reading, never for hashing.
     *
     * @param string $gateway name used in the exception message
     * @param string|array<mixed> $payload
     * @return array<mixed>
     * @throws ValidationException
     */
    public static function decode(string $gateway, string|array $payload): array
    {
        if (is_array($payload)) {
            if ($payload === []) {
                throw ValidationException::make($gateway, self::messages()->empty);
            }

            return $payload;
        }

        if (trim($payload) === '') {
            throw ValidationException::make($gateway, self::messages()->empty);
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            throw ValidationException::make($gateway, self::messages()->json);
        }

        return $decoded;
    }

    /**
     * a date the gateway reported, when it reported one that parses.
     *
     * an unparseable date is not worth an exception: the notification is still
     * valid and the rest of it is still readable, so the field is dropped
     * rather than taking the event down with it.
     *
     * @param mixed $value
     * @return DateTimeImmutable|null
     */
    public static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * a field read as a string id, whatever scalar the gateway sent.
     *
     * ids come as strings in most gateways and as integers in some — Mercado
     * Pago sends `data.id` as a number in its own examples — and the level 9
     * analysis refuses a cast from mixed, so the narrowing happens here once.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function id(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        return is_int($value) ? (string) $value : null;
    }

    /**
     * messages of this request.
     *
     * @return object{empty: string, json: string}
     */
    public static function messages(): object
    {
        return (object) [
            'empty' => 'Informe o corpo da notificação. Corpo vazio não é notificação sem eventos: é integração que não repassou o payload.',
            'json'  => 'O corpo da notificação não é um JSON de objeto ou de lista. Confira se o seu servidor não está alterando o corpo antes de repassá-lo.',
        ];
    }
}
