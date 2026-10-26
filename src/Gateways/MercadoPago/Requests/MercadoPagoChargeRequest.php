<?php

namespace PHPay\MercadoPago\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\ChargeIntent;

class MercadoPagoChargeRequest
{
    /**
     * the Mercado Pago payload for a portable charge description.
     *
     * the amount is absent here because `setAmount()` normalizes it into the
     * array — the Mercado Pago is the other gateway that wants reais, and
     * reading the number straight from the intent would skip that.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException
     */
    public static function fromIntent(ChargeIntent $intent): array
    {
        if ($intent->dueDate !== null) {
            throw ValidationException::make('Mercado Pago', self::messages()->dueDate);
        }

        return [
            'payment_method_id' => 'pix',
            'description'       => $intent->descriptionOrDefault(),
        ] + $intent->extra();
    }

    /**
     * validate charge payload before sending it to the gateway.
     *
     * @param array<mixed> $charge
     * @return void
     * @throws ValidationException
     * @see https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post
     */
    public static function validate(array $charge): void
    {
        $messages = self::messages();

        if (!isset($charge['transaction_amount'])
            || !is_numeric($charge['transaction_amount'])
            || (float) $charge['transaction_amount'] <= 0
        ) {
            throw ValidationException::make('Mercado Pago', $messages->transactionAmount);
        }

        if (!isset($charge['payment_method_id'])
            || !is_string($charge['payment_method_id'])
            || $charge['payment_method_id'] === ''
        ) {
            throw ValidationException::make('Mercado Pago', $messages->paymentMethodId);
        }

        if (!isset($charge['payer']) || !is_array($charge['payer'])) {
            throw ValidationException::make('Mercado Pago', $messages->payer);
        }

        if (!isset($charge['payer']['email'])
            || !is_string($charge['payer']['email'])
            || filter_var($charge['payer']['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::make('Mercado Pago', $messages->payerEmail);
        }
    }

    /**
     * messages for validation
     *
     * @return object{transactionAmount: string, paymentMethodId: string, payer: string, payerEmail: string, dueDate: string}
     */
    public static function messages(): object
    {
        return (object) [
            'transactionAmount' => 'O campo transaction_amount é obrigatório, deve ser numérico e maior que zero.',
            'paymentMethodId'   => 'O campo payment_method_id é obrigatório. Use "pix", "bolbradesco" ou o id devolvido pela tokenização do cartão.',
            'payer'             => 'O campo payer é obrigatório e deve ser um array.',
            'payerEmail'        => 'O campo payer.email é obrigatório e deve ser um e-mail válido.',
            'dueDate'           => 'O Mercado Pago expira cobrança por date_of_expiration, que é data E hora com fuso. Converter dueDate aqui exigiria assumir um fuso horário, e a biblioteca não assume nenhum — informe date_of_expiration por withExtra().',
        ];
    }
}
