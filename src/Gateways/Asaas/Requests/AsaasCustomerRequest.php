<?php

namespace PHPay\Asaas\Requests;

use PHPay\Exceptions\ValidationException;

class AsaasCustomerRequest
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
            throw ValidationException::make('Asaas', $messages->name);
        }

        if (!isset($customer['cpfCnpj']) || !is_string($customer['cpfCnpj']) || trim($customer['cpfCnpj']) === '') {
            throw ValidationException::make('Asaas', $messages->cpfCnpj);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, cpfCnpj: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'    => 'Nome do cliente é obrigatório e deve ser uma string não vazia.',
            'cpfCnpj' => 'CPF/CNPJ do cliente é obrigatório e deve ser uma string não vazia.',
        ];
    }
}
