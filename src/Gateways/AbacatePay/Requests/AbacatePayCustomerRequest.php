<?php

namespace PHPay\AbacatePay\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Customer;

class AbacatePayCustomerRequest
{
    /**
     * validate customer payload before sending it to the gateway.
     *
     * the API marks all four fields as required — there is no partial
     * customer.
     *
     * @param array<mixed> $customer
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $customer): void
    {
        $messages = self::messages();

        if (!isset($customer['name']) || !is_string($customer['name']) || trim($customer['name']) === '') {
            throw ValidationException::make('AbacatePay', $messages->name);
        }

        if (!isset($customer['email'])
            || !is_string($customer['email'])
            || filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::make('AbacatePay', $messages->email);
        }

        if (!isset($customer['cellphone'])
            || !is_string($customer['cellphone'])
            || trim($customer['cellphone']) === ''
        ) {
            throw ValidationException::make('AbacatePay', $messages->cellphone);
        }

        if (!isset($customer['taxId']) || !is_string($customer['taxId']) || trim($customer['taxId']) === '') {
            throw ValidationException::make('AbacatePay', $messages->taxId);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, email: string, cellphone: string, taxId: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'      => 'O campo name é obrigatório e deve ser uma string não vazia.',
            'email'     => 'O campo email é obrigatório e deve ser um e-mail válido.',
            'cellphone' => 'O campo cellphone é obrigatório — o AbacatePay exige celular do cliente.',
            'taxId'     => 'O campo taxId é obrigatório — é o CPF ou CNPJ do cliente.',
        ];
    }

    /**
     * map the library's Customer onto the payload AbacatePay expects.
     *
     * @param Customer $customer
     * @return array<mixed>
     */
    public static function fromCustomer(Customer $customer): array
    {
        return array_filter([
            'name'      => $customer->name,
            'email'     => $customer->email,
            'cellphone' => $customer->phone,
            'taxId'     => $customer->document,
        ], fn ($value) => $value !== null) + $customer->extra();
    }

}
