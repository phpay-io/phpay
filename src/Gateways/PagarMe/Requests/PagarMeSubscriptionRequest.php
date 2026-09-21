<?php

namespace PHPay\PagarMe\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\PagarMe\Enums\{IntervalEnum, PaymentMethodEnum};

class PagarMeSubscriptionRequest
{
    /**
     * validate subscription payload before sending it to the gateway.
     *
     * @param array<mixed> $subscription
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $subscription): void
    {
        $messages = self::messages();

        $hasPlan = isset($subscription['plan_id'])
            && is_string($subscription['plan_id'])
            && $subscription['plan_id'] !== '';

        $hasItems = isset($subscription['items'])
            && is_array($subscription['items'])
            && !empty($subscription['items']);

        if (!$hasPlan && !$hasItems) {
            throw ValidationException::make('Pagar.me', $messages->plan);
        }

        $hasCustomerId = isset($subscription['customer_id'])
            && is_string($subscription['customer_id'])
            && $subscription['customer_id'] !== '';

        if (!$hasCustomerId) {
            if (!isset($subscription['customer']) || !is_array($subscription['customer'])) {
                throw ValidationException::make('Pagar.me', $messages->customer);
            }

            PagarMeCustomerRequest::validate($subscription['customer']);
        }

        if (!isset($subscription['payment_method'])
            || !is_string($subscription['payment_method'])
            || !PaymentMethodEnum::tryFrom($subscription['payment_method']) instanceof PaymentMethodEnum
        ) {
            throw ValidationException::make('Pagar.me', $messages->paymentMethod);
        }

        /* sem plano, a recorrência precisa vir descrita no próprio payload */
        if (!$hasPlan) {
            self::validateRecurrence($subscription, $messages);
        }
    }

    /**
     * validate plan payload before sending it to the gateway.
     *
     * @param array<mixed> $plan
     * @return void
     * @throws ValidationException
     */
    public static function validatePlan(array $plan): void
    {
        $messages = self::messages();

        if (!isset($plan['name']) || !is_string($plan['name']) || trim($plan['name']) === '') {
            throw ValidationException::make('Pagar.me', $messages->planName);
        }

        self::validateRecurrence($plan, $messages);

        if (!isset($plan['items']) || !is_array($plan['items']) || empty($plan['items'])) {
            throw ValidationException::make('Pagar.me', $messages->planItems);
        }

        foreach ($plan['items'] as $item) {
            $scheme = is_array($item) ? ($item['pricing_scheme'] ?? null) : null;

            if (!is_array($scheme)
                || !isset($scheme['price'])
                || !is_int($scheme['price'])
                || $scheme['price'] < 1
            ) {
                throw ValidationException::make('Pagar.me', $messages->planPrice);
            }
        }
    }

    /**
     * validate the interval fields shared by plans and plan-less subscriptions.
     *
     * @param array<mixed> $payload
     * @param object{plan: string, customer: string, paymentMethod: string, interval: string, intervalCount: string, planName: string, planItems: string, planPrice: string} $messages
     * @return void
     * @throws ValidationException
     */
    private static function validateRecurrence(array $payload, object $messages): void
    {
        if (!isset($payload['interval'])
            || !is_string($payload['interval'])
            || !IntervalEnum::tryFrom($payload['interval']) instanceof IntervalEnum
        ) {
            throw ValidationException::make('Pagar.me', $messages->interval);
        }

        if (!isset($payload['interval_count'])
            || !is_int($payload['interval_count'])
            || $payload['interval_count'] < 1
        ) {
            throw ValidationException::make('Pagar.me', $messages->intervalCount);
        }
    }

    /**
     * messages for validation
     *
     * @return object{plan: string, customer: string, paymentMethod: string, interval: string, intervalCount: string, planName: string, planItems: string, planPrice: string}
     */
    public static function messages(): object
    {
        return (object) [
            'plan'          => 'A assinatura precisa de plan_id ou de items próprios. Use setPlan() ou setItems().',
            'customer'      => 'A assinatura precisa de customer_id ou de um customer completo. Use setCustomerId() ou setCustomer().',
            'paymentMethod' => 'O campo payment_method é obrigatório e aceita apenas: credit_card, debit_card, boleto, pix.',
            'interval'      => 'O campo interval é obrigatório e aceita apenas: day, week, month, year.',
            'intervalCount' => 'O campo interval_count é obrigatório e deve ser um inteiro maior que zero.',
            'planName'      => 'O campo name do plano é obrigatório e deve ser uma string não vazia.',
            'planItems'     => 'O plano precisa de ao menos um item em items.',
            'planPrice'     => 'O campo items[].pricing_scheme.price do plano é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. R$ 49,90 é 4990.',
        ];
    }
}
