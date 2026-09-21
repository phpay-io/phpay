<?php

namespace PHPay\PagarMe\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\PagarMe\Enums\CustomerTypeEnum;
use PHPay\Support\Customer;

class PagarMeCustomerRequest
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
            throw ValidationException::make('Pagar.me', $messages->name);
        }

        if (!isset($customer['email'])
            || !is_string($customer['email'])
            || filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::make('Pagar.me', $messages->email);
        }

        if (!isset($customer['document'])
            || !is_string($customer['document'])
            || !in_array(strlen($customer['document']), [11, 14], true)
        ) {
            throw ValidationException::make('Pagar.me', $messages->document);
        }

        if (isset($customer['type'])
            && (!is_string($customer['type'])
                || !CustomerTypeEnum::tryFrom($customer['type']) instanceof CustomerTypeEnum)
        ) {
            throw ValidationException::make('Pagar.me', $messages->type);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, email: string, document: string, type: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'     => 'O campo name é obrigatório e deve ser uma string não vazia.',
            'email'    => 'O campo email é obrigatório e deve ser um e-mail válido.',
            'document' => 'O campo document é obrigatório e deve ter 11 dígitos (CPF) ou 14 (CNPJ), somente números.',
            'type'     => 'O campo type aceita apenas: individual, company.',
        ];
    }

    /**
     * map the library's Customer onto the payload Pagar.me expects.
     *
     * Pagar.me needs `type`, which Customer derives from the document length.
     *
     * @param Customer $customer
     * @return array<mixed>
     */
    public static function fromCustomer(Customer $customer): array
    {
        $payload = array_filter([
            'name'     => $customer->name,
            'email'    => $customer->email,
            'document' => $customer->document,
        ], fn ($value) => $value !== null);

        if ($customer->documentType() !== null) {
            $payload['type'] = $customer->isCompany() ? 'company' : 'individual';
        }

        return $payload + $customer->extra();
    }

}
