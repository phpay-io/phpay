<?php

namespace PHPay\Woovi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Http\Idempotency;
use PHPay\Support\ChargeIntent;

class WooviChargeRequest
{
    /**
     * the Woovi payload for a portable charge description.
     *
     * the amount is absent on purpose: the Woovi takes it in `create()`, not
     * in the body. the `correlationID` is filled when the caller gave no
     * reference, because the Woovi addresses every object by it.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException
     */
    public static function fromIntent(ChargeIntent $intent): array
    {
        if ($intent->dueDate !== null) {
            throw ValidationException::make('Woovi', self::messages()->dueDate);
        }

        return [
            'correlationID' => $intent->reference ?? Idempotency::key(),
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

        if (!isset($charge['correlationID'])
            || !is_string($charge['correlationID'])
            || trim($charge['correlationID']) === ''
        ) {
            throw ValidationException::make('Woovi', $messages->correlationId);
        }

        if (!isset($charge['value']) || !is_int($charge['value']) || $charge['value'] < 1) {
            throw ValidationException::make('Woovi', $messages->value);
        }

        if (isset($charge['customer'])) {
            if (!is_array($charge['customer'])) {
                throw ValidationException::make('Woovi', $messages->customer);
            }

            WooviCustomerRequest::validate($charge['customer']);
        }
    }

    /**
     * messages for validation
     *
     * @return object{correlationId: string, value: string, customer: string, dueDate: string}
     */
    public static function messages(): object
    {
        return (object) [
            'correlationId' => 'O campo correlationID é obrigatório — é o identificador da cobrança no SEU sistema, e é por ele que você consulta depois.',
            'value'         => 'O campo value é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. O Woovi não aceita valor decimal: R$ 100,50 é 10050.',
            'customer'      => 'O campo customer, quando informado, deve ser um array.',
            'dueDate'       => 'A Woovi expira cobrança por DURAÇÃO (expiresIn, em segundos), não por data. Converter dueDate aqui exigiria assumir um fuso horário, e a biblioteca não assume nenhum — informe expiresIn por withExtra().',
        ];
    }
}
