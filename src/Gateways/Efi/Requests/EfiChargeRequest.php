<?php

namespace PHPay\Efi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Money;

class EfiChargeRequest
{
    /**
     * validate charge payload before sending it to the gateway.
     *
     * @param array<mixed> $charge
     * @param array<mixed> $customer already mounted customer payload
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $charge, array $customer): void
    {
        $messages = self::messages();

        if (empty($customer)) {
            throw ValidationException::make('Efí', $messages->customer);
        }

        if (!isset($charge['description']) || !is_string($charge['description'])) {
            throw ValidationException::make('Efí', $messages->description);
        }

        /* cents, as an integer or a Money: a decimal here is read as cents,
           and 100.00 would bill R$ 1,00 */
        $value = $charge['value'] ?? null;

        if ($value instanceof Money ? $value->isZero() : (!is_int($value) || $value < 1)) {
            throw ValidationException::make('Efí', $messages->value);
        }

        if (!isset($charge['expire_at']) || !is_string($charge['expire_at'])) {
            throw ValidationException::make('Efí', $messages->expireAt);
        }

        if ($charge['expire_at'] < date('Y-m-d')) {
            throw ValidationException::make('Efí', $messages->expireAtPast);
        }
    }

    /**
     * messages for validation
     *
     * @return object{customer: string, description: string, value: string, expireAt: string, expireAtPast: string}
     */
    public static function messages(): object
    {
        return (object) [
            'customer'     => 'Um cliente é obrigatório. Use setCustomer() antes de criar a cobrança.',
            'description'  => 'O campo description é obrigatório e deve ser do tipo string.',
            'value'        => 'O campo value é obrigatório e deve ser um inteiro em CENTAVOS maior que zero, ou um Money. A Efí não aceita valor decimal: R$ 10,50 é 1050. Use setAmount(Money::reais(10.50)) para não errar a unidade.',
            'expireAt'     => 'O campo expire_at é obrigatório e deve ser do tipo string (Y-m-d).',
            'expireAtPast' => 'O campo expire_at não pode ser menor que a data atual.',
        ];
    }
}
