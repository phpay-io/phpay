<?php

namespace PHPay\Support;

/**
 * how the payer is meant to pay.
 *
 * the ten gateways agree that a charge has a payment method and disagree on
 * every word for it: Asaas spells it `billingType`, Mercado Pago
 * `payment_method_id`, Cielo `Payment.Type`, Pagar.me
 * `payments[].payment_method`, PagBank splits it into `qr_codes` and
 * `charges`, and Woovi, AbacatePay and Inter never spell it at all because
 * they only do Pix. this enum is the one name.
 *
 * credit and debit are separate cases, not a single `CARD`: Cielo validates
 * `CreditCard` and `DebitCard` as different types and Pagar.me does the same
 * with `credit_card` and `debit_card`, so collapsing them would drop a
 * distinction those two make.
 */
enum Method: string
{
    case PIX         = 'pix';
    case BOLETO      = 'boleto';
    case CREDIT_CARD = 'credit-card';
    case DEBIT_CARD  = 'debit-card';

    /**
     * label used in exception messages.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PIX         => 'Pix',
            self::BOLETO      => 'boleto',
            self::CREDIT_CARD => 'cartão de crédito',
            self::DEBIT_CARD  => 'cartão de débito',
        };
    }

    /**
     * whether paying it needs card data.
     *
     * @return bool
     */
    public function isCard(): bool
    {
        return $this === self::CREDIT_CARD || $this === self::DEBIT_CARD;
    }

    /**
     * the labels of the given methods, for a message that lists them.
     *
     * @param array<int, self> $methods
     * @return string
     */
    public static function labels(array $methods): string
    {
        if ($methods === []) {
            return 'nenhum';
        }

        return implode(', ', array_map(static fn (self $method): string => $method->label(), $methods));
    }
}
