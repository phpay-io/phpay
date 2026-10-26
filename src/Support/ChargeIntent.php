<?php

namespace PHPay\Support;

use PHPay\Exceptions\ValidationException;

/**
 * a charge described once, for any gateway that knows how to read it.
 *
 * the ten gateways disagree on five independent axes — the payment method,
 * the unit of the amount, whether the amount is a scalar or the sum of
 * items, how the customer is attached, and what the integrator's own
 * reference is called. `Money` already settles the unit and `Customer`
 * already settles the customer's content, so this object carries what is
 * left: the method, the amount, the dates and the reference.
 *
 * it is to a charge what `Customer` is to a payer. the translation lives in
 * each gateway's `*ChargeRequest::fromIntent()`, which already holds the
 * knowledge of that schema — never in the caller.
 *
 * the object is immutable, like `Money` and `Customer`: `with...()` returns
 * another one.
 */
final class ChargeIntent
{
    /** @var array<int, Item> */
    private array $items;

    /** @var array<mixed> */
    private array $extra;

    /**
     * @param Method $method
     * @param Money $amount the amount, always authoritative
     * @param Customer|null $customer null where the gateway has no customer
     * @param string|null $dueDate `Y-m-d`
     * @param string|null $description
     * @param string|null $reference the integrator's own id for this charge
     * @param array<int, Item> $items empty when the amount is a scalar
     * @param array<mixed> $extra fields specific to one gateway
     */
    private function __construct(
        public readonly Method $method,
        public readonly Money $amount,
        public readonly ?Customer $customer = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $description = null,
        public readonly ?string $reference = null,
        array $items = [],
        array $extra = [],
    ) {
        $this->items = $items;
        $this->extra = $extra;
    }

    /**
     * a Pix charge.
     *
     * `dueDate` is optional because a Pix charge without one is valid: it is
     * the difference between `cob` and `cobv` in the BACEN spec, and between
     * a QR Code that never expires and one that does.
     *
     * @param Money $amount
     * @param Customer|null $customer
     * @param string|null $dueDate `Y-m-d`
     * @param string|null $description
     * @param string|null $reference
     * @param array<mixed> $extra
     * @return self
     * @throws ValidationException
     */
    public static function pix(
        Money $amount,
        ?Customer $customer = null,
        ?string $dueDate = null,
        ?string $description = null,
        ?string $reference = null,
        array $extra = [],
    ): self {
        self::validate($amount, $dueDate);

        return new self(Method::PIX, $amount, $customer, $dueDate, $description, $reference, [], $extra);
    }

    /**
     * a charge billed by items, where the amount is their sum.
     *
     * three gateways have no amount on the charge at all — the PagBank and
     * the Pagar.me build it from `items`, the AbacatePay from `products` —
     * so for those this is the shape, not a convenience. On the other seven
     * the sum is what goes out, and the line detail is dropped: that is a
     * limit of those APIs, and it is written down rather than silent.
     *
     * @param Method $method
     * @param array<int, Item> $items
     * @param Customer|null $customer
     * @param string|null $dueDate `Y-m-d`
     * @param string|null $description defaults to the first item's name
     * @param string|null $reference
     * @param array<mixed> $extra
     * @return self
     * @throws ValidationException
     */
    public static function comItens(
        Method $method,
        array $items,
        ?Customer $customer = null,
        ?string $dueDate = null,
        ?string $description = null,
        ?string $reference = null,
        array $extra = [],
    ): self {
        $amount = Item::sum($items);

        self::validate($amount, $dueDate);

        return new self($method, $amount, $customer, $dueDate, $description, $reference, array_values($items), $extra);
    }

    /**
     * the items, empty when the amount was given as a scalar.
     *
     * @return array<int, Item>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * the items, or the whole charge as a single one.
     *
     * the three gateways that bill by item refuse a charge with none, so a
     * scalar intent has to become a line. The library already does this in
     * one other place — `Efi\Resources\Charge\Charge::getItems()` builds
     * one item from the description and the value — and doing it here keeps
     * it written once instead of three times.
     *
     * @return array<int, Item>
     */
    public function itemsOrOne(): array
    {
        return $this->items !== []
            ? $this->items
            : [new Item($this->descriptionOrDefault(), $this->amount)];
    }

    /**
     * extra fields for one gateway.
     *
     * it is the escape hatch for what no other gateway has — Cielo's
     * `SoftDescriptor`, a split. a **required** field landing here is a sign
     * the value object is missing something, not a solution.
     *
     * @return array<mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }

    /**
     * a copy carrying extra fields for one gateway.
     *
     * @param array<mixed> $extra
     * @return self
     */
    public function withExtra(array $extra): self
    {
        return new self(
            $this->method,
            $this->amount,
            $this->customer,
            $this->dueDate,
            $this->description,
            $this->reference,
            $this->items,
            array_merge($this->extra, $extra)
        );
    }

    /**
     * a copy carrying this reference, when the intent does not have one.
     *
     * four gateways need the integrator's own id for the charge, under four
     * names — `correlationID`, `MerchantOrderId`, `reference`, `txid` — and
     * generate one when the caller gave none. filling it here keeps what the
     * caller chose and never overwrites it.
     *
     * @param string $reference
     * @return self
     */
    public function withDefaultReference(string $reference): self
    {
        return new self(
            $this->method,
            $this->amount,
            $this->customer,
            $this->dueDate,
            $this->description,
            $this->reference ?? $reference,
            $this->items,
            $this->extra
        );
    }

    /**
     * the description, or a generic one.
     *
     * three gateways require a description and the other seven do not, so
     * leaving it null would make the intent unusable on those three for a
     * field nobody reads.
     *
     * @return string
     */
    public function descriptionOrDefault(): string
    {
        return $this->description ?? ($this->items[0]->name ?? 'Cobrança');
    }

    /**
     * refuse the intent when this gateway does not do that method.
     *
     * it lives here, and not in each gateway, so the message is written once
     * and always names what the gateway *does* accept — the same courtesy
     * NotImplementedException already does for capabilities.
     *
     * @param string $gateway the gateway label, for the message prefix
     * @param array<int, Method> $supported
     * @return void
     * @throws ValidationException
     */
    public function ensureSupportedBy(string $gateway, array $supported): void
    {
        if (in_array($this->method, $supported, true)) {
            return;
        }

        throw ValidationException::make($gateway, sprintf(
            self::messages()->method,
            $this->method->label(),
            Method::labels($supported)
        ));
    }

    /**
     * messages for every refusal above.
     *
     * @return object{amount: string, dueDate: string, dueDatePast: string, method: string}
     */
    public static function messages(): object
    {
        return (object) [
            'amount'      => 'O valor da cobrança precisa ser maior que zero.',
            'dueDate'     => 'A data de vencimento precisa ser uma data no formato Y-m-d, como "2026-11-10".',
            'dueDatePast' => 'A data de vencimento não pode estar no passado.',
            'method'      => 'Este gateway não cria cobrança por %s a partir de um ChargeIntent. Aceita: %s.',
        ];
    }

    /**
     * @param Money $amount
     * @param string|null $dueDate
     * @return void
     * @throws ValidationException
     */
    private static function validate(Money $amount, ?string $dueDate): void
    {
        $messages = self::messages();

        if ($amount->isZero()) {
            throw ValidationException::make('PHPay', $messages->amount);
        }

        if ($dueDate === null) {
            return;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate);

        if ($parsed === false || $parsed->format('Y-m-d') !== $dueDate) {
            throw ValidationException::make('PHPay', $messages->dueDate);
        }

        if ($dueDate < date('Y-m-d')) {
            throw ValidationException::make('PHPay', $messages->dueDatePast);
        }
    }
}
