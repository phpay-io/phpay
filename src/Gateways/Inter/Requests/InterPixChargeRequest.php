<?php

namespace PHPay\Inter\Requests;

use PHPay\Exceptions\ValidationException;

/**
 * Pix charges of the Inter Pix API: immediate (`cob`) and with due date
 * (`cobv`).
 *
 * the patterns are the BACEN ones, which is why they match the Efí ones field
 * for field — both gateways implement the same specification.
 */
class InterPixChargeRequest
{
    /**
     * amount pattern of the BACEN standard
     */
    public const AMOUNT = '/^\d{1,10}\.\d{2}$/';

    /**
     * txid pattern of the BACEN standard, which the Inter reference repeats:
     * `[a-zA-Z0-9]{26,35}`
     */
    public const TXID = '/^[a-zA-Z0-9]{26,35}$/';

    /**
     * validate charge payload before sending it to the gateway.
     *
     * @param array<mixed> $charge
     * @param bool $withDueDate
     * @param string|null $txid
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $charge, bool $withDueDate, ?string $txid = null): void
    {
        $messages = self::messages();

        if ($txid !== null && preg_match(self::TXID, $txid) !== 1) {
            throw ValidationException::make('Inter', $messages->txid);
        }

        $amount = is_array($charge['valor'] ?? null) ? ($charge['valor']['original'] ?? null) : null;

        if (!is_string($amount) || preg_match(self::AMOUNT, $amount) !== 1) {
            throw ValidationException::make('Inter', $messages->amount);
        }

        if (!isset($charge['chave']) || !is_string($charge['chave']) || trim($charge['chave']) === '') {
            throw ValidationException::make('Inter', $messages->key);
        }

        if (isset($charge['devedor'])) {
            InterPixDebtorRequest::validate(is_array($charge['devedor']) ? $charge['devedor'] : []);
        }

        if (!$withDueDate) {
            return;
        }

        if (!isset($charge['devedor'])) {
            throw ValidationException::make('Inter', $messages->debtor);
        }

        $calendar = is_array($charge['calendario'] ?? null) ? $charge['calendario'] : [];

        if (!self::isDate($calendar['dataDeVencimento'] ?? null)) {
            throw ValidationException::make('Inter', $messages->dueDate);
        }
    }

    /**
     * a txid in the BACEN pattern — 32 random hexadecimal characters, which
     * sits inside the 26-to-35 window.
     *
     * @return string
     */
    public static function txid(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * whether the value is a Y-m-d date that exists in the calendar.
     *
     * @param mixed $date
     * @return bool
     */
    public static function isDate(mixed $date): bool
    {
        if (!is_string($date)) {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * messages for validation
     *
     * @return object{txid: string, amount: string, key: string, debtor: string, dueDate: string}
     */
    public static function messages(): object
    {
        return (object) [
            'txid'    => 'O txid deve ter de 26 a 35 caracteres, somente letras e números.',
            'amount'  => 'Informe o valor com setAmount(Money::reais(...)).',
            'key'     => 'Informe a chave Pix que recebe o pagamento com setKey().',
            'debtor'  => 'Cobrança com vencimento exige devedor: use setCustomer().',
            'dueDate' => 'O vencimento deve ser uma data válida no formato Y-m-d.',
        ];
    }
}
