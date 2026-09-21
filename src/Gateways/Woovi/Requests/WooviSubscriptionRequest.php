<?php

namespace PHPay\Woovi\Requests;

use PHPay\Exceptions\ValidationException;

class WooviSubscriptionRequest
{
    /**
     * validate subscription payload before sending it to the gateway.
     *
     * @param array<mixed> $subscription
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $subscription): void
    {
        $messages = self::messages();

        if (!isset($subscription['value']) || !is_int($subscription['value']) || $subscription['value'] < 1) {
            throw ValidationException::make('Woovi', $messages->value);
        }

        $customer = $subscription['customer'] ?? null;

        if (!is_array($customer)) {
            throw ValidationException::make('Woovi', $messages->customer);
        }

        WooviCustomerRequest::validate($customer);

        $dia = $subscription['dayGenerateCharge'] ?? null;

        if ($dia !== null && (!is_int($dia) || $dia < 1 || $dia > 31)) {
            throw ValidationException::make('Woovi', $messages->dayGenerateCharge);
        }
    }

    /**
     * messages for validation
     *
     * @return object{value: string, customer: string, dayGenerateCharge: string}
     */
    public static function messages(): object
    {
        return (object) [
            'value'             => 'O campo value é obrigatório e deve ser um inteiro em CENTAVOS maior que zero.',
            'customer'          => 'A assinatura precisa de um customer. Use setCustomer().',
            'dayGenerateCharge' => 'O campo dayGenerateCharge deve ser um inteiro entre 1 e 31 — é o dia do mês em que a cobrança é gerada.',
        ];
    }
}
