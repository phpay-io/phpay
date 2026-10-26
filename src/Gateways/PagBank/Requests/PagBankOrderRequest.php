<?php

namespace PHPay\PagBank\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\ChargeIntent;

class PagBankOrderRequest
{
    /**
     * the PagBank order for a portable charge description.
     *
     * only the envelope: the items, the customer and the QR Code go in
     * through the Charge's own setters, which already know the shapes.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     */
    public static function fromIntent(ChargeIntent $intent): array
    {
        $order = $intent->reference !== null ? ['reference_id' => $intent->reference] : [];

        return $order + $intent->extra();
    }

    /**
     * validate order payload before sending it to the gateway.
     *
     * @param array<mixed> $order
     * @return void
     * @throws ValidationException
     * @see https://developer.pagbank.com.br/reference/criar-pedido-simples
     */
    public static function validate(array $order): void
    {
        $messages = self::messages();

        self::validateCustomer($order, $messages);
        self::validateItems($order, $messages);

        $hasCharges = isset($order['charges']) && is_array($order['charges']) && !empty($order['charges']);
        $hasQrCodes = isset($order['qr_codes']) && is_array($order['qr_codes']) && !empty($order['qr_codes']);

        if (!$hasCharges && !$hasQrCodes) {
            throw ValidationException::make('PagBank', $messages->payment);
        }

        if ($hasQrCodes && count($order['qr_codes']) > 1) {
            throw ValidationException::make('PagBank', $messages->singleQrCode);
        }

        if ($hasCharges) {
            self::validateAmounts($order['charges'], $messages->chargeAmount);
        }

        if ($hasQrCodes) {
            self::validateAmounts($order['qr_codes'], $messages->qrCodeAmount);
        }
    }

    /**
     * every entry must carry amount.value as an integer in cents.
     *
     * the amount of a charge or a QR Code is what the payer is actually
     * billed — a decimal here would charge a hundredth of the intended value.
     *
     * @param array<mixed> $entries charges or qr_codes of the order
     * @param string $message
     * @return void
     * @throws ValidationException
     */
    private static function validateAmounts(array $entries, string $message): void
    {
        foreach ($entries as $entry) {
            $value = is_array($entry) && isset($entry['amount']) && is_array($entry['amount'])
                ? $entry['amount']['value'] ?? null
                : null;

            if (!is_int($value) || $value < 1) {
                throw ValidationException::make('PagBank', $message);
            }
        }
    }

    /**
     * validate the customer embedded in the order.
     *
     * @param array<mixed> $order
     * @param object{customer: string, customerName: string, customerEmail: string, customerTaxId: string, items: string, itemName: string, itemQuantity: string, itemAmount: string, payment: string, singleQrCode: string, chargeAmount: string, qrCodeAmount: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateCustomer(array $order, object $messages): void
    {
        if (!isset($order['customer']) || !is_array($order['customer'])) {
            throw ValidationException::make('PagBank', $messages->customer);
        }

        $customer = $order['customer'];

        if (!isset($customer['name']) || !is_string($customer['name']) || trim($customer['name']) === '') {
            throw ValidationException::make('PagBank', $messages->customerName);
        }

        if (!isset($customer['email'])
            || !is_string($customer['email'])
            || filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::make('PagBank', $messages->customerEmail);
        }

        if (!isset($customer['tax_id'])
            || !is_string($customer['tax_id'])
            || !in_array(strlen($customer['tax_id']), [11, 14], true)
        ) {
            throw ValidationException::make('PagBank', $messages->customerTaxId);
        }
    }

    /**
     * validate the items of the order.
     *
     * @param array<mixed> $order
     * @param object{customer: string, customerName: string, customerEmail: string, customerTaxId: string, items: string, itemName: string, itemQuantity: string, itemAmount: string, payment: string, singleQrCode: string, chargeAmount: string, qrCodeAmount: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateItems(array $order, object $messages): void
    {
        if (!isset($order['items']) || !is_array($order['items']) || empty($order['items'])) {
            throw ValidationException::make('PagBank', $messages->items);
        }

        foreach ($order['items'] as $item) {
            if (!is_array($item)) {
                throw ValidationException::make('PagBank', $messages->items);
            }

            if (!isset($item['name']) || !is_string($item['name']) || trim($item['name']) === '') {
                throw ValidationException::make('PagBank', $messages->itemName);
            }

            if (!isset($item['quantity']) || !is_int($item['quantity']) || $item['quantity'] < 1) {
                throw ValidationException::make('PagBank', $messages->itemQuantity);
            }

            if (!isset($item['unit_amount']) || !is_int($item['unit_amount']) || $item['unit_amount'] < 1) {
                throw ValidationException::make('PagBank', $messages->itemAmount);
            }
        }
    }

    /**
     * messages for validation
     *
     * @return object{customer: string, customerName: string, customerEmail: string, customerTaxId: string, items: string, itemName: string, itemQuantity: string, itemAmount: string, payment: string, singleQrCode: string, chargeAmount: string, qrCodeAmount: string}
     */
    public static function messages(): object
    {
        return (object) [
            'customer'      => 'O campo customer é obrigatório e deve ser um array. Use setCustomer().',
            'customerName'  => 'O campo customer.name é obrigatório e deve ser uma string não vazia.',
            'customerEmail' => 'O campo customer.email é obrigatório e deve ser um e-mail válido.',
            'customerTaxId' => 'O campo customer.tax_id é obrigatório e deve ter 11 dígitos (CPF) ou 14 (CNPJ), somente números.',
            'items'         => 'O pedido precisa de ao menos um item em items. Use setItems() ou addItem().',
            'itemName'      => 'O campo items[].name é obrigatório e deve ser uma string não vazia.',
            'itemQuantity'  => 'O campo items[].quantity é obrigatório e deve ser um inteiro maior que zero.',
            'itemAmount'    => 'O campo items[].unit_amount é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. O PagBank não aceita valor decimal: R$ 10,50 é 1050.',
            'payment'       => 'O pedido precisa de charges (cartão ou boleto) ou de um qr_code (Pix). Use setCharges() ou setQrCode().',
            'singleQrCode'  => 'O PagBank aceita apenas um QR Code por pedido.',
            'chargeAmount'  => 'O campo charges[].amount.value é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. O PagBank não aceita valor decimal: R$ 10,50 é 1050.',
            'qrCodeAmount'  => 'O campo qr_codes[].amount.value é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. Use setQrCode(Money::reais(10.50)) para não errar a unidade.',
        ];
    }
}
