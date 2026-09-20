<?php

namespace PHPay\MercadoPago\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\MercadoPago\Enums\FrequencyTypeEnum;

class MercadoPagoSubscriptionRequest
{
    /**
     * validate subscription payload before sending it to the gateway.
     *
     * @param array<mixed> $subscription
     * @return void
     * @throws ValidationException
     * @see https://www.mercadopago.com.br/developers/en/reference/subscriptions/_preapproval/post
     */
    public static function validate(array $subscription): void
    {
        $messages = self::messages();

        if (!isset($subscription['payer_email'])
            || !is_string($subscription['payer_email'])
            || filter_var($subscription['payer_email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::make('Mercado Pago', $messages->payerEmail);
        }

        if (!isset($subscription['back_url'])
            || !is_string($subscription['back_url'])
            || filter_var($subscription['back_url'], FILTER_VALIDATE_URL) === false
        ) {
            throw ValidationException::make('Mercado Pago', $messages->backUrl);
        }

        /* com plano associado, a recorrência vem do próprio plano */
        if (isset($subscription['preapproval_plan_id'])) {
            return;
        }

        if (!isset($subscription['reason']) || !is_string($subscription['reason'])) {
            throw ValidationException::make('Mercado Pago', $messages->reason);
        }

        if (!isset($subscription['auto_recurring']) || !is_array($subscription['auto_recurring'])) {
            throw ValidationException::make('Mercado Pago', $messages->autoRecurring);
        }

        $recurring = $subscription['auto_recurring'];

        if (!isset($recurring['frequency']) || !is_int($recurring['frequency']) || $recurring['frequency'] < 1) {
            throw ValidationException::make('Mercado Pago', $messages->frequency);
        }

        if (!isset($recurring['frequency_type'])
            || !is_string($recurring['frequency_type'])
            || !FrequencyTypeEnum::tryFrom($recurring['frequency_type']) instanceof FrequencyTypeEnum
        ) {
            throw ValidationException::make('Mercado Pago', $messages->frequencyType);
        }

        if (!isset($recurring['transaction_amount'])
            || !is_numeric($recurring['transaction_amount'])
            || (float) $recurring['transaction_amount'] <= 0
        ) {
            throw ValidationException::make('Mercado Pago', $messages->transactionAmount);
        }

        if (!isset($recurring['currency_id']) || !is_string($recurring['currency_id'])) {
            throw ValidationException::make('Mercado Pago', $messages->currencyId);
        }
    }

    /**
     * messages for validation
     *
     * @return object{payerEmail: string, backUrl: string, reason: string, autoRecurring: string, frequency: string, frequencyType: string, transactionAmount: string, currencyId: string}
     */
    public static function messages(): object
    {
        return (object) [
            'payerEmail'        => 'O campo payer_email é obrigatório e deve ser um e-mail válido.',
            'backUrl'           => 'O campo back_url é obrigatório e deve ser uma URL válida.',
            'reason'            => 'O campo reason é obrigatório quando a assinatura não usa um plano associado.',
            'autoRecurring'     => 'O campo auto_recurring é obrigatório quando a assinatura não usa um plano associado.',
            'frequency'         => 'O campo auto_recurring.frequency é obrigatório e deve ser um inteiro maior que zero.',
            'frequencyType'     => 'O campo auto_recurring.frequency_type é obrigatório e aceita apenas: days, months.',
            'transactionAmount' => 'O campo auto_recurring.transaction_amount é obrigatório, numérico e maior que zero.',
            'currencyId'        => 'O campo auto_recurring.currency_id é obrigatório (ex.: BRL).',
        ];
    }
}
