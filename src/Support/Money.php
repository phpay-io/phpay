<?php

namespace PHPay\Support;

use PHPay\Exceptions\ValidationException;

/**
 * a monetary value, held in cents.
 *
 * Brazilian gateways disagree on the unit they expect: Asaas and Mercado Pago
 * take reais as a decimal, while PagBank, Pagar.me, Cielo, Rede, AbacatePay,
 * Woovi and Efí take cents as an integer. Getting that wrong does not break
 * the integration — it charges the wrong amount, silently.
 *
 * This type ends that. You say which unit you have, the gateway asks for the
 * unit it needs, and neither side can be wrong:
 *
 *     Money::reais(100.50)      // or Money::reais('100,50')
 *     Money::centavos(10050)
 */
final class Money
{
    /**
     * construct
     *
     * @param int $cents
     */
    private function __construct(
        private readonly int $cents
    ) {
    }

    /**
     * build from an amount in cents.
     *
     * @param int $cents
     * @return self
     * @throws ValidationException
     */
    public static function centavos(int $cents): self
    {
        if ($cents < 0) {
            throw ValidationException::make('PHPay', self::messages()->negative);
        }

        return new self($cents);
    }

    /**
     * build from an amount in reais.
     *
     * accepts a float, an int, or a string in either notation — `'100.50'`
     * and `'100,50'` both work, which is what a form field usually hands you.
     *
     * a value with more than two decimal places is refused rather than
     * rounded: silent rounding is how one-cent reconciliation bugs are born.
     * Round it yourself, or use centavos().
     *
     * @param int|float|string $amount
     * @return self
     * @throws ValidationException
     */
    public static function reais(int|float|string $amount): self
    {
        $normalized = self::normalize($amount);

        if ($normalized < 0) {
            throw ValidationException::make('PHPay', self::messages()->negative);
        }

        $cents = $normalized * 100;

        if (abs($cents - round($cents)) > 0.000001) {
            throw ValidationException::make('PHPay', self::messages()->precision);
        }

        return new self((int) round($cents));
    }

    /**
     * the amount in cents, for the gateways that take an integer.
     *
     * @return int
     */
    public function toCentavos(): int
    {
        return $this->cents;
    }

    /**
     * the amount in reais, for the gateways that take a decimal.
     *
     * @return float
     */
    public function toReais(): float
    {
        return round($this->cents / 100, 2);
    }

    /**
     * the amount in reais as a decimal string with a dot: "1234.56".
     *
     * the shape the BACEN Pix standard expects in `valor.original`. built with
     * integer arithmetic, so no float ever gets between the cents and the text.
     *
     * @return string
     */
    public function toDecimal(): string
    {
        return intdiv($this->cents, 100) . '.' . str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * multiply by a whole number of units — a line of N identical products.
     *
     * @param int $times
     * @return self
     * @throws ValidationException
     */
    public function multiply(int $times): self
    {
        if ($times < 0) {
            throw ValidationException::make('PHPay', self::messages()->negativeMultiplier);
        }

        return new self($this->cents * $times);
    }

    /**
     * add another amount.
     *
     * @param self $other
     * @return self
     */
    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /**
     * whether two amounts are the same.
     *
     * @param self $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * whether the amount is zero.
     *
     * @return bool
     */
    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    /**
     * the amount written the way a Brazilian reads it.
     *
     * @return string
     */
    public function format(): string
    {
        return 'R$ ' . number_format($this->toReais(), 2, ',', '.');
    }

    /**
     * take whatever a caller passed — a Money, an int, a float — and give
     * back the cents a gateway needs.
     *
     * a bare int is read as cents, which is what the gateways that use this
     * helper already expected before Money existed.
     *
     * @param self|int $amount
     * @return int
     * @throws ValidationException
     */
    public static function asCentavos(self|int $amount): int
    {
        return $amount instanceof self ? $amount->toCentavos() : self::centavos($amount)->toCentavos();
    }

    /**
     * take whatever a caller passed and give back the reais a gateway needs.
     *
     * a bare int or float is read as reais, which is what the gateways that
     * use this helper already expected before Money existed.
     *
     * @param self|int|float $amount
     * @return float
     * @throws ValidationException
     */
    public static function asReais(self|int|float $amount): float
    {
        return $amount instanceof self ? $amount->toReais() : self::reais($amount)->toReais();
    }

    /**
     * messages for validation
     *
     * @return object{negative: string, precision: string, notNumeric: string, negativeMultiplier: string}
     */
    public static function messages(): object
    {
        return (object) [
            'negative'           => 'Um valor monetário não pode ser negativo.',
            'precision'          => 'Um valor em reais não pode ter mais de duas casas decimais. Arredonde antes, ou use Money::centavos() para ser exato.',
            'notNumeric'         => 'O valor precisa ser numérico. Aceita float, int ou string em qualquer notação: "100.50" ou "100,50".',
            'negativeMultiplier' => 'A quantidade usada em multiply() não pode ser negativa.',
        ];
    }

    /**
     * turn whatever came in into a float in reais.
     *
     * @param int|float|string $amount
     * @return float
     * @throws ValidationException
     */
    private static function normalize(int|float|string $amount): float
    {
        if (is_string($amount)) {
            $limpo = trim($amount);

            /* "1.234,56" vira "1234.56"; "100,50" vira "100.50" */
            if (str_contains($limpo, ',')) {
                $limpo = str_replace(['.', ','], ['', '.'], $limpo);
            }

            if (!is_numeric($limpo)) {
                throw ValidationException::make('PHPay', self::messages()->notNumeric);
            }

            return (float) $limpo;
        }

        return (float) $amount;
    }
}
