<?php

namespace PHPay\Efi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Money;

class EfiChargeRequest
{
    /**
     * validate charge payload before sending it to the gateway.
     *
     * `description` and `value` belong to the **single-item shortcut**, not to
     * the gateway: Efí's payload has no charge-level description or amount —
     * it has `items`. So they are only required when no item list was given,
     * and demanding them alongside explicit items would be asking for fields
     * that go nowhere.
     *
     * @param array<mixed> $charge
     * @param array<mixed> $customer already mounted customer payload
     * @param bool $hasItems whether setItems() supplied the items
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $charge, array $customer, bool $hasItems = false): void
    {
        $messages = self::messages();

        if (empty($customer)) {
            throw ValidationException::make('Efí', $messages->customer);
        }

        if (!$hasItems) {
            if (!isset($charge['description']) || !is_string($charge['description'])) {
                throw ValidationException::make('Efí', $messages->description);
            }

            /* cents, as an integer or a Money: a decimal here is read as cents,
               and 100.00 would bill R$ 1,00 */
            $value = $charge['value'] ?? null;

            if ($value instanceof Money ? $value->isZero() : (!is_int($value) || $value < 1)) {
                throw ValidationException::make('Efí', $messages->value);
            }
        }

        if (!isset($charge['expire_at']) || !is_string($charge['expire_at'])) {
            throw ValidationException::make('Efí', $messages->expireAt);
        }

        if ($charge['expire_at'] < date('Y-m-d')) {
            throw ValidationException::make('Efí', $messages->expireAtPast);
        }
    }

    /**
     * validate the items of a charge.
     *
     * @param array<mixed> $items
     * @return void
     * @throws ValidationException
     */
    public static function validateItems(array $items): void
    {
        $messages = self::messages();

        if ($items === [] || !array_is_list($items)) {
            throw ValidationException::make('Efí', $messages->items);
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw ValidationException::make('Efí', $messages->items);
            }

            if (!isset($item['name']) || !is_string($item['name']) || trim($item['name']) === '') {
                throw ValidationException::make('Efí', $messages->itemName);
            }

            /* cents, like the charge value — a decimal here bills the wrong amount */
            if (!isset($item['value']) || !is_int($item['value']) || $item['value'] < 1) {
                throw ValidationException::make('Efí', $messages->itemValue);
            }
        }
    }

    /**
     * validate the `configurations` of a banking billet.
     *
     * **only what Efí documents is checked**, and unknown keys pass through.
     * That is the opposite of `GatewayConfigRequest`, which refuses a key it
     * does not know — and the asymmetry is deliberate: there the array is the
     * library's own surface, so a typo is a mistake; here it is the gateway's
     * payload, and refusing a field this version does not model would break
     * whoever sends one that works.
     *
     * @param array<mixed> $configurations
     * @return void
     * @throws ValidationException
     */
    public static function validateConfigurations(array $configurations): void
    {
        $messages = self::messages();

        if ($configurations === []) {
            throw ValidationException::make('Efí', $messages->configurationsEmpty);
        }

        foreach (['fine', 'interest'] as $field) {
            if (!array_key_exists($field, $configurations)) {
                continue;
            }

            $value = $configurations[$field];

            /* `interest` also takes the object form {value, type} */
            if ($field === 'interest' && is_array($value)) {
                self::validateInterestObject($value);

                continue;
            }

            if (!is_int($value) || $value < 0) {
                throw ValidationException::make('Efí', $messages->configurationsScale);
            }
        }

        if (!array_key_exists('days_to_write_off', $configurations)) {
            return;
        }

        $days = $configurations['days_to_write_off'];

        if (!is_int($days) || $days < 0 || $days > 120) {
            throw ValidationException::make('Efí', $messages->daysToWriteOff);
        }
    }

    /**
     * validate the object form of `interest`.
     *
     * @param array<mixed> $interest
     * @return void
     * @throws ValidationException
     */
    private static function validateInterestObject(array $interest): void
    {
        $messages = self::messages();

        if (!isset($interest['value']) || !is_int($interest['value']) || $interest['value'] < 0) {
            throw ValidationException::make('Efí', $messages->configurationsScale);
        }

        if (!array_key_exists('type', $interest)) {
            return;
        }

        if (!in_array($interest['type'], ['daily', 'monthly'], true)) {
            throw ValidationException::make('Efí', $messages->interestType);
        }
    }

    /**
     * messages for validation
     *
     * @return object{
     *     customer: string,
     *     description: string,
     *     value: string,
     *     expireAt: string,
     *     expireAtPast: string,
     *     items: string,
     *     itemName: string,
     *     itemValue: string,
     *     configurationsEmpty: string,
     *     configurationsScale: string,
     *     daysToWriteOff: string,
     *     interestType: string
     * }
     */
    public static function messages(): object
    {
        return (object) [
            'customer'     => 'Um cliente é obrigatório. Use setCustomer() antes de criar a cobrança.',
            'description'  => 'O campo description é obrigatório e deve ser do tipo string.',
            'value'        => 'O campo value é obrigatório e deve ser um inteiro em CENTAVOS maior que zero, ou um Money. A Efí não aceita valor decimal: R$ 10,50 é 1050. Use setAmount(Money::reais(10.50)) para não errar a unidade.',
            'expireAt'     => 'O campo expire_at é obrigatório e deve ser do tipo string (Y-m-d).',
            'expireAtPast' => 'O campo expire_at não pode ser menor que a data atual.',
            'items'        => 'Informe os itens como uma lista não vazia de arrays. Para um item só, continua valendo setAmount() com a description da cobrança.',
            'itemName'     => 'Cada item precisa de name, como string não vazia.',
            'itemValue'    => 'Cada item precisa de value, um inteiro em CENTAVOS maior que zero. R$ 10,50 é 1050.',

            'configurationsEmpty' => 'Array vazio não diz "sem multa nem juros", e a Efí não documenta se o objeto configurations pode ser omitido. Para cobrança sem encargo, informe fine e interest como 0.',
            'configurationsScale' => 'fine e interest são inteiros na escala da Efí, e as duas escalas são DIFERENTES: fine é percentual × 100 (200 = 2%) e interest é percentual × 1000 (33 = 0,033% ao dia). Por isso não aceitam decimal — 2,5% de multa é 250.',
            'daysToWriteOff'      => 'days_to_write_off é um inteiro de 0 a 120 dias.',
            'interestType'        => 'interest.type aceita apenas "daily" ou "monthly". Omitido, a Efí calcula ao dia.',
        ];
    }
}
