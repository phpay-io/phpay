<?php

namespace PHPay\Gateways\Asaas\Resources\Subscription\Requests;

use PHPay\Gateways\Asaas\Enums\{BillingTypeEnum};

class StoreSubscriptionAsaasRequest
{
    /**
     * validate customer and charge data
     *
     * @param array<mixed> $charge
     * @return void
     */
    public static function validate(array $charge): void
    {
        if (!isset($charge['customer']) && !is_string($charge['customer'])) {
            /** @phpstan-ignore property.notFound */
            throw new \InvalidArgumentException(self::messages()->charge->customer, 400);
        }

        if (!isset($charge['billingType'])) {
            /** @phpstan-ignore property.notFound */
            throw new \InvalidArgumentException(self::messages()->charge->billingType, 400);
        }

        if (!BillingTypeEnum::tryFrom($charge['billingType'])) {
            /** @phpstan-ignore property.notFound */
            throw new \InvalidArgumentException(self::messages()->charge->billingType, 400);
        }

        if (!isset($charge['value']) && !is_numeric($charge['value'])) {
            /** @phpstan-ignore property.notFound */
            throw new \InvalidArgumentException(self::messages()->charge->value, 400);
        }

        if (!isset($charge['nextDueDate']) && !is_string($charge['nextDueDate'])) {
            /** @phpstan-ignore property.notFound */
            throw new \InvalidArgumentException(self::messages()->charge->nextDueDate, 400);
        }
    }

    /**
     * messages for validation
     *
     * @return object
     */
    public static function messages(): object
    {
        return (object) [
            'customer' => (object) [
                'id' => 'Asaas: O identificador do customer é obrigatório.',
            ],
            'charge' => (object) [
                'customer'    => 'Asaas: O campo customer é obrigatório e deve ser do tipo string.',
                'billingType' => 'Asaas: O campo billingType é obrigatório, e tem como disponível as seguintes opções: UNDEFINED, BOLETO, CREDIT_CARD, PIX',
                'value'       => 'Asaas: O campo value é obrigatório e deve ser do tipo numérico.',
                'nextDueDate' => 'Asaas: O campo nextDueDate é obrigatório e deve ser do tipo string.',
            ],
        ];
    }
}
