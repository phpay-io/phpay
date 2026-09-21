<?php

namespace PHPay\Efi\Requests;

use PHPay\Efi\Enums\{AccountTypeEnum, PeriodicityEnum};
use PHPay\Exceptions\ValidationException;

/**
 * Pix Automático: the recurrence (`rec`) and its charges (`cobr`).
 */
class EfiSubscriptionRequest
{
    /**
     * validate recurrence payload before sending it to the gateway.
     *
     * @param array<mixed> $recurrence
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $recurrence): void
    {
        $messages = self::messages();
        $bond     = is_array($recurrence['vinculo'] ?? null) ? $recurrence['vinculo'] : [];

        if (!self::isText($bond['contrato'] ?? null, 35)) {
            throw ValidationException::make('Efí', $messages->contract);
        }

        if (isset($bond['objeto']) && !self::isText($bond['objeto'], 35)) {
            throw ValidationException::make('Efí', $messages->description);
        }

        if (!isset($bond['devedor'])) {
            throw ValidationException::make('Efí', $messages->debtor);
        }

        EfiPixDebtorRequest::validate(is_array($bond['devedor']) ? $bond['devedor'] : []);

        $calendar = is_array($recurrence['calendario'] ?? null) ? $recurrence['calendario'] : [];

        if (!EfiPixChargeRequest::isDate($calendar['dataInicial'] ?? null)) {
            throw ValidationException::make('Efí', $messages->startDate);
        }

        if (isset($calendar['dataFinal']) && !EfiPixChargeRequest::isDate($calendar['dataFinal'])) {
            throw ValidationException::make('Efí', $messages->endDate);
        }

        $periodicity = $calendar['periodicidade'] ?? null;

        if (!is_string($periodicity) || PeriodicityEnum::tryFrom($periodicity) === null) {
            throw ValidationException::make('Efí', $messages->periodicity);
        }

        $amount = is_array($recurrence['valor'] ?? null) ? $recurrence['valor'] : [];

        if (!isset($amount['valorRec']) && !isset($amount['valorMinimoRecebedor'])) {
            throw ValidationException::make('Efí', $messages->amount);
        }

        if (!in_array($recurrence['politicaRetentativa'] ?? null, ['NAO_PERMITE', 'PERMITE_3R_7D'], true)) {
            throw ValidationException::make('Efí', $messages->retry);
        }
    }

    /**
     * validate the payload of a charge of a recurrence.
     *
     * @param array<mixed> $charge
     * @return void
     * @throws ValidationException
     */
    public static function validateCharge(array $charge): void
    {
        $messages = self::messages();

        if (!self::isText($charge['idRec'] ?? null, 35)) {
            throw ValidationException::make('Efí', $messages->recurrence);
        }

        $calendar = is_array($charge['calendario'] ?? null) ? $charge['calendario'] : [];

        if (!EfiPixChargeRequest::isDate($calendar['dataDeVencimento'] ?? null)) {
            throw ValidationException::make('Efí', EfiPixChargeRequest::messages()->dueDate);
        }

        $receiver = is_array($charge['recebedor'] ?? null) ? $charge['recebedor'] : [];
        $type     = $receiver['tipoConta'] ?? null;

        if (!self::isText($receiver['conta'] ?? null, 20)
            || !is_string($type)
            || AccountTypeEnum::tryFrom($type) === null
        ) {
            throw ValidationException::make('Efí', $messages->receiver);
        }
    }

    /**
     * messages for validation
     *
     * @return object{contract: string, description: string, debtor: string, startDate: string, endDate: string, periodicity: string, amount: string, retry: string, recurrence: string, receiver: string}
     */
    public static function messages(): object
    {
        return (object) [
            'contract'    => 'Informe o contrato com setContract() — até 35 caracteres, é o que o pagador vê no app do banco.',
            'description' => 'A descrição (objeto) aceita até 35 caracteres.',
            'debtor'      => 'A recorrência exige devedor: use setCustomer().',
            'startDate'   => 'Informe o início com setPeriodicity(): data válida no formato Y-m-d.',
            'endDate'     => 'A data final deve ser uma data válida no formato Y-m-d.',
            'periodicity' => 'Periodicidade inválida: use PeriodicityEnum.',
            'amount'      => 'Informe o valor fixo com setAmount() ou o mínimo, para valor variável, com setMinimumAmount().',
            'retry'       => 'A política de retentativa deve ser NAO_PERMITE ou PERMITE_3R_7D.',
            'recurrence'  => 'Informe o idRec da recorrência que a cobrança pertence.',
            'receiver'    => 'A cobrança de Pix Automático exige a conta recebedora: use setReceiver().',
        ];
    }

    /**
     * non-empty string up to a length, counted in characters.
     *
     * the regex counts UTF-8 characters, so "Assinatura mensal" and
     * "Serviço de manutenção" measure the same way — without ext-mbstring.
     *
     * @param mixed $value
     * @param int $max
     * @return bool
     */
    private static function isText(mixed $value, int $max): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && preg_match('/^.{1,' . $max . '}$/us', $value) === 1;
    }
}
