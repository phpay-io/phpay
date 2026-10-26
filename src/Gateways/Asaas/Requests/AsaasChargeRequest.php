<?php

namespace PHPay\Asaas\Requests;

use PHPay\Asaas\Enums\BillingTypeEnum;
use PHPay\Exceptions\ValidationException;
use PHPay\Support\ChargeIntent;

class AsaasChargeRequest
{
    /**
     * the Asaas payload for a portable charge description.
     *
     * the amount goes out in reais, because the Asaas is one of the two
     * gateways that want a decimal — `Money` is what keeps that from being
     * a guess. the customer is NOT here: the Asaas attaches it by id, and
     * the Charge resource already knows how to reuse or create one.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException
     */
    public static function fromIntent(ChargeIntent $intent): array
    {
        $messages = self::messages();

        if ($intent->dueDate === null) {
            throw ValidationException::make('Asaas', $messages->dueDateRequired);
        }

        return [
            'billingType' => BillingTypeEnum::PIX->value,
            'value'       => $intent->amount->toReais(),
            'dueDate'     => $intent->dueDate,
            'description' => $intent->descriptionOrDefault(),
        ] + $intent->extra();
    }

    /**
     * validate charge payload before sending it to the gateway.
     *
     * @param array<mixed> $charge
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $charge): void
    {
        $messages = self::messages();

        if (!isset($charge['customer']) || !is_string($charge['customer']) || $charge['customer'] === '') {
            throw ValidationException::make('Asaas', $messages->customer);
        }

        if (!isset($charge['billingType'])
            || !is_string($charge['billingType'])
            || !BillingTypeEnum::tryFrom($charge['billingType']) instanceof BillingTypeEnum
        ) {
            throw ValidationException::make('Asaas', $messages->billingType);
        }

        if (!isset($charge['value']) || !is_numeric($charge['value']) || (float) $charge['value'] <= 0) {
            throw ValidationException::make('Asaas', $messages->value);
        }

        if (!isset($charge['dueDate']) || !is_string($charge['dueDate'])) {
            throw ValidationException::make('Asaas', $messages->dueDate);
        }
    }

    /**
     * messages for validation
     *
     * @return object{customer: string, billingType: string, value: string, dueDate: string, dueDateRequired: string}
     */
    public static function messages(): object
    {
        return (object) [
            'customer'        => 'O campo customer é obrigatório e deve ser do tipo string.',
            'billingType'     => 'O campo billingType é obrigatório, e tem como disponível as seguintes opções: UNDEFINED, BOLETO, CREDIT_CARD, PIX.',
            'value'           => 'O campo value é obrigatório, deve ser numérico e maior que zero.',
            'dueDate'         => 'O campo dueDate é obrigatório e deve ser do tipo string.',
            'dueDateRequired' => 'O Asaas exige data de vencimento mesmo no Pix, então informe dueDate no ChargeIntent. É limite do gateway, não da biblioteca: inventar uma data aqui criaria cobrança com vencimento que ninguém escolheu.',
        ];
    }
}
