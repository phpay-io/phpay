<?php

namespace PHPay\Efi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Customer;

class EfiCustomerRequest
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
            throw ValidationException::make('Efí', $messages->name);
        }

        if (!isset($customer['cpf_cnpj']) || !is_string($customer['cpf_cnpj'])) {
            throw ValidationException::make('Efí', $messages->cpfCnpj);
        }

        if (!in_array(strlen($customer['cpf_cnpj']), [11, 14], true)) {
            throw ValidationException::make('Efí', $messages->cpfCnpjLength);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, cpfCnpj: string, cpfCnpjLength: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'          => 'Nome é obrigatório e deve ser uma string não vazia.',
            'cpfCnpj'       => 'CPF/CNPJ é obrigatório e deve ser do tipo string.',
            'cpfCnpjLength' => 'CPF/CNPJ deve conter 11 dígitos (CPF) ou 14 dígitos (CNPJ), somente números.',
        ];
    }

    /**
     * map the library's Customer onto the payload Efí expects.
     *
     * @param Customer $customer
     * @return array<mixed>
     */
    public static function fromCustomer(Customer $customer): array
    {
        return array_filter([
            'name'         => $customer->name,
            'cpf_cnpj'     => $customer->document,
            'email'        => $customer->email,
            'phone_number' => $customer->phone,
        ], fn ($value) => $value !== null) + $customer->extra();
    }

}
