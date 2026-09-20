<?php

namespace PHPay\MercadoPago\Requests;

use PHPay\Exceptions\ValidationException;

class MercadoPagoCustomerRequest
{
    /**
     * validate customer payload before sending it to the gateway.
     *
     * @param array<mixed> $customer
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $customer): void
    {
        $messages = self::messages();

        if (!isset($customer['email'])
            || !is_string($customer['email'])
            || filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::make('Mercado Pago', $messages->email);
        }
    }

    /**
     * messages for validation
     *
     * @return object{email: string}
     */
    public static function messages(): object
    {
        return (object) [
            'email' => 'O campo email é obrigatório e deve ser um e-mail válido.',
        ];
    }
}
