<?php

namespace PHPay\AbacatePay\Requests;

use PHPay\AbacatePay\Enums\{BillingFrequencyEnum, BillingMethodEnum};
use PHPay\Exceptions\ValidationException;

class AbacatePayBillingRequest
{
    /**
     * smallest price the API accepts per product, in cents
     */
    private const MINIMUM_PRICE = 100;

    /**
     * validate billing payload before sending it to the gateway.
     *
     * @param array<mixed> $billing
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $billing): void
    {
        $messages = self::messages();

        self::validateFrequencyAndMethods($billing, $messages);
        self::validateProducts($billing, $messages);
        self::validateUrls($billing, $messages);
        self::validateCustomer($billing, $messages);
    }

    /**
     * @param array<mixed> $billing
     * @param object{frequency: string, methods: string, products: string, productFields: string, price: string, returnUrl: string, completionUrl: string, customer: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateFrequencyAndMethods(array $billing, object $messages): void
    {
        if (!isset($billing['frequency'])
            || !is_string($billing['frequency'])
            || !BillingFrequencyEnum::tryFrom($billing['frequency']) instanceof BillingFrequencyEnum
        ) {
            throw ValidationException::make('AbacatePay', $messages->frequency);
        }

        $methods = $billing['methods'] ?? null;

        if (!is_array($methods) || count($methods) !== 1) {
            throw ValidationException::make('AbacatePay', $messages->methods);
        }

        foreach ($methods as $method) {
            if (!is_string($method) || !BillingMethodEnum::tryFrom($method) instanceof BillingMethodEnum) {
                throw ValidationException::make('AbacatePay', $messages->methods);
            }
        }
    }

    /**
     * @param array<mixed> $billing
     * @param object{frequency: string, methods: string, products: string, productFields: string, price: string, returnUrl: string, completionUrl: string, customer: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateProducts(array $billing, object $messages): void
    {
        $products = $billing['products'] ?? null;

        if (!is_array($products) || empty($products)) {
            throw ValidationException::make('AbacatePay', $messages->products);
        }

        foreach ($products as $product) {
            if (!is_array($product)) {
                throw ValidationException::make('AbacatePay', $messages->products);
            }

            foreach (['externalId', 'name'] as $field) {
                if (!isset($product[$field]) || !is_string($product[$field]) || trim($product[$field]) === '') {
                    throw ValidationException::make('AbacatePay', $messages->productFields);
                }
            }

            if (!isset($product['quantity']) || !is_int($product['quantity']) || $product['quantity'] < 1) {
                throw ValidationException::make('AbacatePay', $messages->productFields);
            }

            if (!isset($product['price'])
                || !is_int($product['price'])
                || $product['price'] < self::MINIMUM_PRICE
            ) {
                throw ValidationException::make('AbacatePay', $messages->price);
            }
        }
    }

    /**
     * @param array<mixed> $billing
     * @param object{frequency: string, methods: string, products: string, productFields: string, price: string, returnUrl: string, completionUrl: string, customer: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateUrls(array $billing, object $messages): void
    {
        foreach (['returnUrl' => 'returnUrl', 'completionUrl' => 'completionUrl'] as $field => $message) {
            if (!isset($billing[$field])
                || !is_string($billing[$field])
                || filter_var($billing[$field], FILTER_VALIDATE_URL) === false
            ) {
                throw ValidationException::make('AbacatePay', $messages->{$message});
            }
        }
    }

    /**
     * @param array<mixed> $billing
     * @param object{frequency: string, methods: string, products: string, productFields: string, price: string, returnUrl: string, completionUrl: string, customer: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateCustomer(array $billing, object $messages): void
    {
        $hasCustomerId = isset($billing['customerId'])
            && is_string($billing['customerId'])
            && $billing['customerId'] !== '';

        if ($hasCustomerId) {
            return;
        }

        $customer = $billing['customer'] ?? null;

        if (!is_array($customer)) {
            throw ValidationException::make('AbacatePay', $messages->customer);
        }

        AbacatePayCustomerRequest::validate($customer);
    }

    /**
     * messages for validation
     *
     * @return object{frequency: string, methods: string, products: string, productFields: string, price: string, returnUrl: string, completionUrl: string, customer: string}
     */
    public static function messages(): object
    {
        return (object) [
            'frequency'     => 'O campo frequency aceita apenas ONE_TIME — o AbacatePay não tem cobrança recorrente.',
            'methods'       => 'O campo methods aceita exatamente um método, e hoje só PIX é suportado.',
            'products'      => 'A cobrança precisa de ao menos um produto em products. Use addProduct().',
            'productFields' => 'Cada produto precisa de externalId, name e quantity — o externalId é o id do produto no SEU sistema, e precisa ser único.',
            'price'         => 'O campo products[].price é obrigatório e deve ser um inteiro em CENTAVOS de no mínimo 100 (R$ 1,00). O AbacatePay não aceita valor decimal: R$ 20,00 é 2000.',
            'returnUrl'     => 'O campo returnUrl é obrigatório e deve ser uma URL válida — para onde o cliente volta se desistir.',
            'completionUrl' => 'O campo completionUrl é obrigatório e deve ser uma URL válida — para onde o cliente vai após pagar.',
            'customer'      => 'A cobrança precisa de customerId ou de um customer completo. Use setCustomerId() ou setCustomer().',
        ];
    }
}
