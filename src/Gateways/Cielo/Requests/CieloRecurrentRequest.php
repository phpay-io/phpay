<?php

namespace PHPay\Cielo\Requests;

use PHPay\Cielo\Enums\RecurrentIntervalEnum;
use PHPay\Exceptions\ValidationException;

class CieloRecurrentRequest
{
    /**
     * validate recurrence payload before sending it to the gateway.
     *
     * @param array<mixed> $sale
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $sale): void
    {
        $messages = self::messages();

        CieloSaleRequest::validate($sale);

        $payment = $sale['Payment'] ?? null;

        if (!is_array($payment)) {
            throw ValidationException::make('Cielo', CieloSaleRequest::messages()->payment);
        }

        $card = $payment['CreditCard'] ?? null;

        if (!is_array($card) || empty($card)) {
            throw ValidationException::make('Cielo', $messages->card);
        }

        $recurrent = $payment['RecurrentPayment'] ?? null;

        if (!is_array($recurrent)) {
            throw ValidationException::make('Cielo', $messages->recurrent);
        }

        if (!isset($recurrent['Interval'])
            || !is_string($recurrent['Interval'])
            || !RecurrentIntervalEnum::tryFrom($recurrent['Interval']) instanceof RecurrentIntervalEnum
        ) {
            throw ValidationException::make('Cielo', $messages->interval);
        }
    }

    /**
     * validate an amount given in cents.
     *
     * @param int $amount
     * @return void
     * @throws ValidationException
     */
    public static function validateAmount(int $amount): void
    {
        if ($amount < 1) {
            throw ValidationException::make('Cielo', self::messages()->amount);
        }
    }

    /**
     * messages for validation
     *
     * @return object{card: string, recurrent: string, interval: string, amount: string}
     */
    public static function messages(): object
    {
        return (object) [
            'card'      => 'A recorrência da Cielo exige cartão de crédito. Use setCard() antes de criar.',
            'recurrent' => 'O bloco Payment.RecurrentPayment é obrigatório numa recorrência.',
            'interval'  => 'O campo Interval aceita apenas: Monthly, Bimonthly, Quarterly, SemiAnnual, Annual.',
            'amount'    => 'O valor deve ser um inteiro em CENTAVOS maior que zero. R$ 157,00 é 15700.',
        ];
    }
}
