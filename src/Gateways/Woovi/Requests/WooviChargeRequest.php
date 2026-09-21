<?php

namespace PHPay\Woovi\Requests;

use PHPay\Exceptions\ValidationException;

class WooviChargeRequest
{
    /**
     * validate charge payload before sending it to the gateway.
     *
     * @param array<mixed> $charge
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $charge): void
    {
        $messages = self::messages();

        if (!isset($charge['correlationID'])
            || !is_string($charge['correlationID'])
            || trim($charge['correlationID']) === ''
        ) {
            throw ValidationException::make('Woovi', $messages->correlationId);
        }

        if (!isset($charge['value']) || !is_int($charge['value']) || $charge['value'] < 1) {
            throw ValidationException::make('Woovi', $messages->value);
        }

        if (isset($charge['customer'])) {
            if (!is_array($charge['customer'])) {
                throw ValidationException::make('Woovi', $messages->customer);
            }

            WooviCustomerRequest::validate($charge['customer']);
        }
    }

    /**
     * messages for validation
     *
     * @return object{correlationId: string, value: string, customer: string}
     */
    public static function messages(): object
    {
        return (object) [
            'correlationId' => 'O campo correlationID é obrigatório — é o identificador da cobrança no SEU sistema, e é por ele que você consulta depois.',
            'value'         => 'O campo value é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. O Woovi não aceita valor decimal: R$ 100,50 é 10050.',
            'customer'      => 'O campo customer, quando informado, deve ser um array.',
        ];
    }
}
