<?php

namespace PHPay\Woovi\Requests;

use PHPay\Exceptions\ValidationException;

class WooviCustomerRequest
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

        if (!isset($customer['name']) || !is_string($customer['name']) || trim($customer['name']) === '') {
            throw ValidationException::make('Woovi', $messages->name);
        }

        $temEmail = isset($customer['email'])
            && is_string($customer['email'])
            && filter_var($customer['email'], FILTER_VALIDATE_EMAIL) !== false;

        $temTaxId = isset($customer['taxID'])
            && (is_string($customer['taxID']) || is_array($customer['taxID']));

        $temTelefone = isset($customer['phone'])
            && is_string($customer['phone'])
            && trim($customer['phone']) !== '';

        if (!$temEmail && !$temTaxId && !$temTelefone) {
            throw ValidationException::make('Woovi', $messages->identificador);
        }

        if (isset($customer['email']) && !$temEmail) {
            throw ValidationException::make('Woovi', $messages->email);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, email: string, identificador: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'          => 'O campo name é obrigatório e deve ser uma string não vazia.',
            'email'         => 'O campo email, quando informado, deve ser um e-mail válido.',
            'identificador' => 'O cliente precisa de ao menos um identificador: email, taxID ou phone.',
        ];
    }
}
