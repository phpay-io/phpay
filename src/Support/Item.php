<?php

namespace PHPay\Support;

use PHPay\Exceptions\ValidationException;

/**
 * one line of a charge that is billed by items.
 *
 * three of the ten gateways do not have an amount on the charge at all —
 * PagBank and Pagar.me build it from `items`, AbacatePay from `products` —
 * and each spells the line differently: `name`/`unit_amount` in the PagBank,
 * `description`/`amount` in the Pagar.me, `name`/`price` in the AbacatePay.
 * this is the one shape.
 *
 * the unit price is a `Money` for the same reason the charge's amount is:
 * all three want cents, and the two that want reais would read the same
 * number as a hundredfold.
 */
final class Item
{
    /**
     * @param string $name
     * @param Money $unitAmount price of ONE unit, not of the line
     * @param int $quantity
     * @param string|null $reference the item's id in YOUR system
     * @throws ValidationException
     */
    public function __construct(
        public readonly string $name,
        public readonly Money $unitAmount,
        public readonly int $quantity = 1,
        public readonly ?string $reference = null,
    ) {
        $messages = self::messages();

        if (trim($name) === '') {
            throw ValidationException::make('PHPay', $messages->name);
        }

        if ($quantity < 1) {
            throw ValidationException::make('PHPay', $messages->quantity);
        }

        if ($unitAmount->isZero()) {
            throw ValidationException::make('PHPay', $messages->unitAmount);
        }
    }

    /**
     * what this line costs: unit price times quantity.
     *
     * @return Money
     */
    public function total(): Money
    {
        return $this->unitAmount->multiply($this->quantity);
    }

    /**
     * what the whole list costs.
     *
     * this is the boundary where a caller's array becomes items, so the
     * parameter is described as what actually arrives — unvalidated — and
     * the check below is what makes the promise true. It is the same shape
     * the `*Request::validate()` classes use.
     *
     * @param array<mixed> $items
     * @return Money
     * @throws ValidationException
     */
    public static function sum(array $items): Money
    {
        if ($items === []) {
            throw ValidationException::make('PHPay', self::messages()->empty);
        }

        $total = Money::centavos(0);

        foreach ($items as $item) {
            if (!$item instanceof self) {
                throw ValidationException::make('PHPay', self::messages()->type);
            }

            $total = $total->plus($item->total());
        }

        return $total;
    }

    /**
     * messages for every refusal above.
     *
     * @return object{name: string, quantity: string, unitAmount: string, empty: string, type: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'       => 'O item precisa de um nome, porque os três gateways que cobram por item exigem um.',
            'quantity'   => 'A quantidade do item precisa ser um inteiro maior que zero.',
            'unitAmount' => 'O preço unitário do item precisa ser maior que zero. É o preço de UMA unidade, não o da linha.',
            'empty'      => 'A lista de itens não pode estar vazia: os três gateways que cobram por item recusam cobrança sem nenhum.',
            'type'       => 'Cada item precisa ser um PHPay\Support\Item.',
        ];
    }
}
