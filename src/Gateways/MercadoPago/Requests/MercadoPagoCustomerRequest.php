<?php

namespace PHPay\MercadoPago\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Customer;

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

    /**
     * map the library's Customer onto the payer Mercado Pago expects.
     *
     * Mercado Pago wants the name split in two and the document inside an
     * identification object.
     *
     * @param Customer $customer
     * @return array<mixed>
     */
    public static function fromCustomer(Customer $customer): array
    {
        $payload = array_filter([
            'email'      => $customer->email,
            'first_name' => $customer->firstName(),
            'last_name'  => $customer->lastName(),
        ], fn ($value) => $value !== null);

        if ($customer->documentType() !== null) {
            $payload['identification'] = [
                'type'   => $customer->documentType(),
                'number' => $customer->document,
            ];
        }

        return $payload + $customer->extra();
    }

}
