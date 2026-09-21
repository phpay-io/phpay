<?php

namespace PHPay\Asaas\Resources\Subscription\Requests;

use PHPay\Exceptions\ValidationException;

/**
 * the settings Asaas uses to issue an invoice (nota fiscal) for each charge of
 * a subscription.
 */
class SubscriptionInvoiceSettingsAsaasRequest
{
    /**
     * taxes the Asaas API requires, even when zero
     */
    private const TAXES = ['retainIss', 'iss', 'pis', 'cofins', 'csll', 'inss', 'ir'];

    /**
     * values the Asaas API accepts in `effectiveDatePeriod`
     */
    private const PERIODS = [
        'ON_PAYMENT_CONFIRMATION',
        'ON_PAYMENT_DUE_DATE',
        'BEFORE_PAYMENT_DUE_DATE',
        'ON_DUE_DATE_MONTH',
        'ON_NEXT_MONTH',
    ];

    /**
     * validate invoice settings payload before sending it to the gateway.
     *
     * @param array<mixed> $settings
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $settings): void
    {
        $messages = self::messages();
        $taxes    = $settings['taxes'] ?? null;

        if (!is_array($taxes)) {
            throw ValidationException::make('Asaas', $messages->taxes);
        }

        foreach (self::TAXES as $tax) {
            if (!array_key_exists($tax, $taxes)) {
                throw ValidationException::make('Asaas', sprintf($messages->tax, $tax));
            }
        }

        if (!is_bool($taxes['retainIss'])) {
            throw ValidationException::make('Asaas', $messages->retainIss);
        }

        foreach (array_diff(self::TAXES, ['retainIss']) as $tax) {
            if (!is_int($taxes[$tax]) && !is_float($taxes[$tax])) {
                throw ValidationException::make('Asaas', sprintf($messages->rate, $tax));
            }
        }

        if (isset($settings['effectiveDatePeriod'])
            && !in_array($settings['effectiveDatePeriod'], self::PERIODS, true)
        ) {
            throw ValidationException::make('Asaas', $messages->period);
        }
    }

    /**
     * messages for validation
     *
     * @return object{taxes: string, tax: string, retainIss: string, rate: string, period: string}
     */
    public static function messages(): object
    {
        return (object) [
            'taxes'     => 'O campo taxes é obrigatório, com retainIss, iss, pis, cofins, csll, inss e ir.',
            'tax'       => 'Falta o imposto %s em taxes — informe 0 quando não houver.',
            'retainIss' => 'O campo taxes.retainIss deve ser booleano.',
            'rate'      => 'O imposto %s deve ser a alíquota em percentual, como número.',
            'period'    => 'O effectiveDatePeriod deve ser ON_PAYMENT_CONFIRMATION, ON_PAYMENT_DUE_DATE, BEFORE_PAYMENT_DUE_DATE, ON_DUE_DATE_MONTH ou ON_NEXT_MONTH.',
        ];
    }
}
