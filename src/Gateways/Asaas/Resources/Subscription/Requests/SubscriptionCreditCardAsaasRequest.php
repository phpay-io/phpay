<?php

namespace PHPay\Asaas\Resources\Subscription\Requests;

use PHPay\Exceptions\ValidationException;

/**
 * the card that replaces the one of a subscription, without charging it.
 */
class SubscriptionCreditCardAsaasRequest
{
    /**
     * fields the Asaas API requires in `creditCard`
     */
    private const CARD = ['holderName', 'number', 'expiryMonth', 'expiryYear', 'ccv'];

    /**
     * fields the Asaas API requires in `creditCardHolderInfo`
     */
    private const HOLDER = ['name', 'email', 'cpfCnpj', 'postalCode', 'addressNumber', 'phone'];

    /**
     * validate card payload before sending it to the gateway.
     *
     * either a `creditCardToken` or the card with its holder — and always the
     * `remoteIp` of the buyer, which Asaas uses in its fraud analysis.
     *
     * @param array<mixed> $card
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $card): void
    {
        $messages = self::messages();

        if (!isset($card['remoteIp'])
            || !is_string($card['remoteIp'])
            || filter_var($card['remoteIp'], FILTER_VALIDATE_IP) === false
        ) {
            throw ValidationException::make('Asaas', $messages->remoteIp);
        }

        if (isset($card['creditCardToken']) && is_string($card['creditCardToken']) && $card['creditCardToken'] !== '') {
            return;
        }

        if (!self::hasAll($card['creditCard'] ?? null, self::CARD)) {
            throw ValidationException::make('Asaas', $messages->creditCard);
        }

        if (!self::hasAll($card['creditCardHolderInfo'] ?? null, self::HOLDER)) {
            throw ValidationException::make('Asaas', $messages->holder);
        }
    }

    /**
     * messages for validation
     *
     * @return object{remoteIp: string, creditCard: string, holder: string}
     */
    public static function messages(): object
    {
        return (object) [
            'remoteIp'   => 'O campo remoteIp é obrigatório e deve ser o IP de quem está pagando.',
            'creditCard' => 'Informe creditCardToken, ou creditCard com holderName, number, expiryMonth, expiryYear e ccv.',
            'holder'     => 'O creditCardHolderInfo precisa de name, email, cpfCnpj, postalCode, addressNumber e phone.',
        ];
    }

    /**
     * whether the value is an array with every field filled.
     *
     * @param mixed $value
     * @param array<int, string> $fields
     * @return bool
     */
    private static function hasAll(mixed $value, array $fields): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($fields as $field) {
            if (!isset($value[$field]) || $value[$field] === '') {
                return false;
            }
        }

        return true;
    }
}
