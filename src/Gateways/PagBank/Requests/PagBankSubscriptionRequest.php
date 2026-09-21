<?php

namespace PHPay\PagBank\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\PagBank\Enums\IntervalUnitEnum;

class PagBankSubscriptionRequest
{
    /**
     * validate subscription payload before sending it to the gateway.
     *
     * @param array<mixed> $subscription
     * @return void
     * @throws ValidationException
     * @see https://developer.pagbank.com.br/reference/criar-assinatura
     */
    public static function validate(array $subscription): void
    {
        $messages = self::messages();

        $plan = $subscription['plan'] ?? null;

        if (!is_array($plan) || !isset($plan['id']) || !is_string($plan['id']) || $plan['id'] === '') {
            throw ValidationException::make('PagBank', $messages->plan);
        }

        $customer = $subscription['customer'] ?? null;

        if (!is_array($customer)) {
            throw ValidationException::make('PagBank', $messages->customer);
        }

        /* um assinante já criado entra só pelo id */
        if (isset($customer['id'])) {
            if (!is_string($customer['id']) || $customer['id'] === '') {
                throw ValidationException::make('PagBank', $messages->customerId);
            }

            return;
        }

        PagBankCustomerRequest::validate($customer);
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
            throw ValidationException::make('PagBank', $messages->planName);
        }

        $amount = $plan['amount'] ?? null;

        if (!is_array($amount)
            || !isset($amount['value'])
            || !is_int($amount['value'])
            || $amount['value'] < 1
        ) {
            throw ValidationException::make('PagBank', $messages->planAmount);
        }

        $interval = $plan['interval'] ?? null;

        if (!is_array($interval)) {
            throw ValidationException::make('PagBank', $messages->planInterval);
        }

        if (!isset($interval['unit'])
            || !is_string($interval['unit'])
            || !IntervalUnitEnum::tryFrom($interval['unit']) instanceof IntervalUnitEnum
        ) {
            throw ValidationException::make('PagBank', $messages->planIntervalUnit);
        }

        if (!isset($interval['length']) || !is_int($interval['length']) || $interval['length'] < 1) {
            throw ValidationException::make('PagBank', $messages->planIntervalLength);
        }
    }

    /**
     * messages for validation
     *
     * @return object{plan: string, customer: string, customerId: string, planName: string, planAmount: string, planInterval: string, planIntervalUnit: string, planIntervalLength: string}
     */
    public static function messages(): object
    {
        return (object) [
            'plan'               => 'A assinatura precisa de um plano. Use setPlan() com o id de um plano existente.',
            'customer'           => 'A assinatura precisa de um assinante. Use setCustomerId() ou setCustomer().',
            'customerId'         => 'O campo customer.id deve ser uma string não vazia.',
            'planName'           => 'O campo name do plano é obrigatório e deve ser uma string não vazia.',
            'planAmount'         => 'O campo amount.value do plano é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. R$ 49,90 é 4990.',
            'planInterval'       => 'O campo interval do plano é obrigatório e deve ser um array.',
            'planIntervalUnit'   => 'O campo interval.unit do plano aceita apenas: DAYS, MONTHS, YEARS.',
            'planIntervalLength' => 'O campo interval.length do plano é obrigatório e deve ser um inteiro maior que zero.',
        ];
    }
}
