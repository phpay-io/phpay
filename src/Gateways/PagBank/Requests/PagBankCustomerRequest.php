<?php

namespace PHPay\PagBank\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Customer;

class PagBankCustomerRequest
{
    /**
     * validate subscriber payload before sending it to the gateway.
     *
     * @param array<mixed> $customer
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $customer): void
    {
        $messages = self::messages();

        if (!isset($customer['name']) || !is_string($customer['name']) || trim($customer['name']) === '') {
            throw ValidationException::make('PagBank', $messages->name);
        }

        if (!isset($customer['email'])
            || !is_string($customer['email'])
            || filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::make('PagBank', $messages->email);
        }

        if (!isset($customer['tax_id'])
            || !is_string($customer['tax_id'])
            || !in_array(strlen($customer['tax_id']), [11, 14], true)
        ) {
            throw ValidationException::make('PagBank', $messages->taxId);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, email: string, taxId: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'  => 'O campo name é obrigatório e deve ser uma string não vazia.',
            'email' => 'O campo email é obrigatório e deve ser um e-mail válido.',
            'taxId' => 'O campo tax_id é obrigatório e deve ter 11 dígitos (CPF) ou 14 (CNPJ), somente números.',
        ];
    }

    /**
     * map the library's Customer onto the payload PagBank expects.
     *
     * PagBank wants the phone split into country, area and number, which is
     * what Customer::phoneParts() produces.
     *
     * @param Customer $customer
     * @return array<mixed>
     */
    public static function fromCustomer(Customer $customer): array
    {
        $payload = array_filter([
            'name'   => $customer->name,
            'email'  => $customer->email,
            'tax_id' => $customer->document,
        ], fn ($value) => $value !== null);

        $telefone = $customer->phoneParts();

        if ($telefone !== null) {
            $payload['phones'] = [$telefone + ['type' => 'MOBILE']];
        }

        return $payload + $customer->extra();
    }

}
