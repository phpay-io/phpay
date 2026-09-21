<?php

namespace PHPay\PagarMe\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\PagarMe\Enums\PaymentMethodEnum;

class PagarMeOrderRequest
{
    /**
     * validate order payload before sending it to the gateway.
     *
     * @param array<mixed> $order
     * @return void
     * @throws ValidationException
     * @see https://docs.pagar.me/reference/criar-pedido-2
     */
    public static function validate(array $order): void
    {
        $messages = self::messages();

        self::validateItems($order, $messages);
        self::validateCustomer($order, $messages);
        self::validatePayments($order, $messages);
    }

    /**
     * @param array<mixed> $order
     * @param object{items: string, itemDescription: string, itemQuantity: string, itemAmount: string, customer: string, payments: string, paymentMethod: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateItems(array $order, object $messages): void
    {
        if (!isset($order['items']) || !is_array($order['items']) || empty($order['items'])) {
            throw ValidationException::make('Pagar.me', $messages->items);
        }

        foreach ($order['items'] as $item) {
            if (!is_array($item)) {
                throw ValidationException::make('Pagar.me', $messages->items);
            }

            if (!isset($item['description'])
                || !is_string($item['description'])
                || trim($item['description']) === ''
            ) {
                throw ValidationException::make('Pagar.me', $messages->itemDescription);
            }

            if (!isset($item['quantity']) || !is_int($item['quantity']) || $item['quantity'] < 1) {
                throw ValidationException::make('Pagar.me', $messages->itemQuantity);
            }

            if (!isset($item['amount']) || !is_int($item['amount']) || $item['amount'] < 1) {
                throw ValidationException::make('Pagar.me', $messages->itemAmount);
            }
        }
    }

    /**
     * @param array<mixed> $order
     * @param object{items: string, itemDescription: string, itemQuantity: string, itemAmount: string, customer: string, payments: string, paymentMethod: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateCustomer(array $order, object $messages): void
    {
        $hasCustomerId = isset($order['customer_id'])
            && is_string($order['customer_id'])
            && $order['customer_id'] !== '';

        if ($hasCustomerId) {
            return;
        }

        if (!isset($order['customer']) || !is_array($order['customer'])) {
            throw ValidationException::make('Pagar.me', $messages->customer);
        }

        PagarMeCustomerRequest::validate($order['customer']);
    }

    /**
     * @param array<mixed> $order
     * @param object{items: string, itemDescription: string, itemQuantity: string, itemAmount: string, customer: string, payments: string, paymentMethod: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validatePayments(array $order, object $messages): void
    {
        if (!isset($order['payments']) || !is_array($order['payments']) || empty($order['payments'])) {
            throw ValidationException::make('Pagar.me', $messages->payments);
        }

        foreach ($order['payments'] as $payment) {
            if (!is_array($payment)
                || !isset($payment['payment_method'])
                || !is_string($payment['payment_method'])
                || !PaymentMethodEnum::tryFrom($payment['payment_method']) instanceof PaymentMethodEnum
            ) {
                throw ValidationException::make('Pagar.me', $messages->paymentMethod);
            }
        }
    }

    /**
     * messages for validation
     *
     * @return object{items: string, itemDescription: string, itemQuantity: string, itemAmount: string, customer: string, payments: string, paymentMethod: string}
     */
    public static function messages(): object
    {
        return (object) [
            'items'           => 'O pedido precisa de ao menos um item em items. Use setItems() ou addItem().',
            'itemDescription' => 'O campo items[].description é obrigatório e deve ser uma string não vazia.',
            'itemQuantity'    => 'O campo items[].quantity é obrigatório e deve ser um inteiro maior que zero.',
            'itemAmount'      => 'O campo items[].amount é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. O Pagar.me não aceita valor decimal: R$ 10,50 é 1050.',
            'customer'        => 'O pedido precisa de customer_id ou de um customer completo. Use setCustomerId() ou setCustomer().',
            'payments'        => 'O pedido precisa de ao menos uma forma de pagamento em payments. Use setPix(), setBoleto() ou setPayments().',
            'paymentMethod'   => 'O campo payments[].payment_method é obrigatório e aceita apenas: credit_card, debit_card, boleto, pix.',
        ];
    }
}
