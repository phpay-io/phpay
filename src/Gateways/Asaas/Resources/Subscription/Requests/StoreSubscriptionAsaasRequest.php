<?php

namespace PHPay\Asaas\Resources\Subscription\Requests;

use PHPay\Asaas\Enums\{BillingTypeEnum, SubscriptionCycleEnum};
use PHPay\Exceptions\ValidationException;

class StoreSubscriptionAsaasRequest
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

        if (!isset($subscription['customer'])
            || !is_string($subscription['customer'])
            || $subscription['customer'] === ''
        ) {
            throw ValidationException::make('Asaas', $messages->customer);
        }

        if (!isset($subscription['billingType'])
            || !is_string($subscription['billingType'])
            || !BillingTypeEnum::tryFrom($subscription['billingType']) instanceof BillingTypeEnum
        ) {
            throw ValidationException::make('Asaas', $messages->billingType);
        }

        if (!isset($subscription['value'])
            || !is_numeric($subscription['value'])
            || (float) $subscription['value'] <= 0
        ) {
            throw ValidationException::make('Asaas', $messages->value);
        }

        if (!isset($subscription['nextDueDate']) || !is_string($subscription['nextDueDate'])) {
            throw ValidationException::make('Asaas', $messages->nextDueDate);
        }

        if (!isset($subscription['cycle'])
            || !is_string($subscription['cycle'])
            || !SubscriptionCycleEnum::tryFrom($subscription['cycle']) instanceof SubscriptionCycleEnum
        ) {
            throw ValidationException::make('Asaas', $messages->cycle);
        }
    }

    /**
     * messages for validation
     *
     * @return object{customer: string, billingType: string, value: string, nextDueDate: string, cycle: string}
     */
    public static function messages(): object
    {
        return (object) [
            'customer'    => 'O campo customer é obrigatório e deve ser do tipo string. Use setCustomer() ou setCustomerId() antes de criar a assinatura.',
            'billingType' => 'O campo billingType é obrigatório, e tem como disponível as seguintes opções: UNDEFINED, BOLETO, CREDIT_CARD, PIX.',
            'value'       => 'O campo value é obrigatório, deve ser numérico e maior que zero.',
            'nextDueDate' => 'O campo nextDueDate é obrigatório e deve ser do tipo string.',
            'cycle'       => 'O campo cycle é obrigatório: use setCycle() com SubscriptionCycleEnum (WEEKLY, BIWEEKLY, MONTHLY, BIMONTHLY, QUARTERLY, SEMIANNUALLY ou YEARLY).',
        ];
    }
}
