<?php

namespace PHPay\Asaas\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Customer;

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

    /**
     * map the library's Customer onto the payload Asaas expects.
     *
     * @param Customer $customer
     * @return array<mixed>
     */
    public static function fromCustomer(Customer $customer): array
    {
        return array_filter([
            'name'        => $customer->name,
            'cpfCnpj'     => $customer->document,
            'email'       => $customer->email,
            'mobilePhone' => $customer->phone,
        ], fn ($value) => $value !== null) + $customer->extra();
    }

}
