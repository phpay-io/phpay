<?php

namespace PHPay\Cielo\Requests;

use PHPay\Cielo\Enums\PaymentTypeEnum;
use PHPay\Exceptions\ValidationException;

class CieloSaleRequest
{
    /**
     * validate sale payload before sending it to the gateway.
     *
     * @param array<mixed> $sale
     * @return void
     * @throws ValidationException
     * @see https://developercielo.github.io/manual/cielo-ecommerce
     */
    public static function validate(array $sale): void
    {
        $messages = self::messages();

        if (!isset($sale['MerchantOrderId'])
            || !is_string($sale['MerchantOrderId'])
            || trim($sale['MerchantOrderId']) === ''
        ) {
            throw ValidationException::make('Cielo', $messages->merchantOrderId);
        }

        if (!isset($sale['Customer']) || !is_array($sale['Customer'])) {
            throw ValidationException::make('Cielo', $messages->customer);
        }

        if (!isset($sale['Customer']['Name'])
            || !is_string($sale['Customer']['Name'])
            || trim($sale['Customer']['Name']) === ''
        ) {
            throw ValidationException::make('Cielo', $messages->customerName);
        }

        if (!isset($sale['Payment']) || !is_array($sale['Payment'])) {
            throw ValidationException::make('Cielo', $messages->payment);
        }

        $payment = $sale['Payment'];

        if (!isset($payment['Type'])
            || !is_string($payment['Type'])
            || !PaymentTypeEnum::tryFrom($payment['Type']) instanceof PaymentTypeEnum
        ) {
            throw ValidationException::make('Cielo', $messages->paymentType);
        }

        if (!isset($payment['Amount']) || !is_int($payment['Amount']) || $payment['Amount'] < 1) {
            throw ValidationException::make('Cielo', $messages->amount);
        }
    }

    /**
     * messages for validation
     *
     * @return object{merchantOrderId: string, customer: string, customerName: string, payment: string, paymentType: string, amount: string}
     */
    public static function messages(): object
    {
        return (object) [
            'merchantOrderId' => 'O campo MerchantOrderId é obrigatório — é o identificador do pedido no seu sistema.',
            'customer'        => 'O campo Customer é obrigatório e deve ser um array. Use setCustomer().',
            'customerName'    => 'O campo Customer.Name é obrigatório e deve ser uma string não vazia.',
            'payment'         => 'O campo Payment é obrigatório. Use setPix(), setBoleto() ou setCreditCard().',
            'paymentType'     => 'O campo Payment.Type é obrigatório e aceita apenas: CreditCard, DebitCard, Pix, Boleto.',
            'amount'          => 'O campo Payment.Amount é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. A Cielo não aceita valor decimal: R$ 157,00 é 15700.',
        ];
    }
}
