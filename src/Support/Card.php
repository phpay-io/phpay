<?php

namespace PHPay\Support;

use PHPay\Exceptions\ValidationException;

/**
 * a card, as the gateways that accept the number itself want it.
 *
 * **it is not the whole card story, and cannot be.** Two of the ten take the
 * number on the server — Cielo in `Payment.CreditCard`, Rede in flat fields —
 * while the Mercado Pago takes a token its own browser SDK mints, so the
 * number never reaches the server at all, and the PagBank and the Pagar.me
 * prefer an encrypted blob or a token. There is no single object that is
 * honest about all of those, so this one describes the first case and the
 * gateways that cannot use it simply do not declare the method.
 *
 * the number is checked with Luhn before any request, because a typo caught
 * here costs nothing and a typo caught at the gateway costs a refusal on
 * someone's checkout.
 */
final class Card
{
    /**
     * @param string $number digits only; punctuation and spaces are stripped
     * @param string $holderName as printed on the card
     * @param string $expirationMonth `01`-`12`
     * @param string $expirationYear four digits
     * @param string $securityCode CVV, three or four digits
     * @param string|null $brand when the gateway wants it named
     * @throws ValidationException
     */
    public function __construct(
        #[\SensitiveParameter] public readonly string $number,
        public readonly string $holderName,
        public readonly string $expirationMonth,
        public readonly string $expirationYear,
        #[\SensitiveParameter] public readonly string $securityCode,
        public readonly ?string $brand = null,
    ) {
        $messages = self::messages();

        if (!self::passesLuhn($number)) {
            throw ValidationException::make('PHPay', $messages->number);
        }

        if (trim($holderName) === '') {
            throw ValidationException::make('PHPay', $messages->holderName);
        }

        if (preg_match('/^(0[1-9]|1[0-2])$/', $expirationMonth) !== 1) {
            throw ValidationException::make('PHPay', $messages->expirationMonth);
        }

        if (preg_match('/^\d{4}$/', $expirationYear) !== 1) {
            throw ValidationException::make('PHPay', $messages->expirationYear);
        }

        if (preg_match('/^\d{3,4}$/', $securityCode) !== 1) {
            throw ValidationException::make('PHPay', $messages->securityCode);
        }
    }

    /**
     * build a Card, stripping spaces and punctuation from the number.
     *
     * use this when the value comes from a form: `'4111 1111 1111 1111'`
     * becomes digits.
     *
     * @param string $number
     * @param string $holderName
     * @param string $expirationMonth
     * @param string $expirationYear
     * @param string $securityCode
     * @param string|null $brand
     * @return self
     * @throws ValidationException
     */
    public static function make(
        #[\SensitiveParameter] string $number,
        string $holderName,
        string $expirationMonth,
        string $expirationYear,
        #[\SensitiveParameter] string $securityCode,
        ?string $brand = null,
    ): self {
        return new self(
            (string) preg_replace('/\D/', '', $number),
            $holderName,
            str_pad($expirationMonth, 2, '0', STR_PAD_LEFT),
            $expirationYear,
            $securityCode,
            $brand
        );
    }

    /**
     * the last four digits, which is what a receipt may show.
     *
     * @return string
     */
    public function lastFour(): string
    {
        return substr($this->number, -4);
    }

    /**
     * `MM/YYYY`, which is how Cielo wants the expiry.
     *
     * @return string
     */
    public function expiration(): string
    {
        return $this->expirationMonth . '/' . $this->expirationYear;
    }

    /**
     * never let the number or the CVV into a dump.
     *
     * @return array<string, string|null>
     */
    public function __debugInfo(): array
    {
        return [
            'number'          => '**** **** **** ' . $this->lastFour(),
            'holderName'      => $this->holderName,
            'expirationMonth' => $this->expirationMonth,
            'expirationYear'  => $this->expirationYear,
            'securityCode'    => '***',
            'brand'           => $this->brand,
        ];
    }

    /**
     * messages for every refusal above.
     *
     * @return object{number: string, holderName: string, expirationMonth: string, expirationYear: string, securityCode: string}
     */
    public static function messages(): object
    {
        return (object) [
            'number'          => 'O número do cartão é inválido: ele não passa no dígito verificador (Luhn). Conferir aqui custa nada; conferir no gateway custa uma recusa no checkout de alguém.',
            'holderName'      => 'O nome impresso no cartão é obrigatório.',
            'expirationMonth' => 'O mês de validade precisa ser de "01" a "12".',
            'expirationYear'  => 'O ano de validade precisa ter quatro dígitos, como "2030".',
            'securityCode'    => 'O código de segurança precisa ter três ou quatro dígitos.',
        ];
    }

    /**
     * @param string $number
     * @return bool
     */
    private static function passesLuhn(string $number): bool
    {
        if (preg_match('/^\d{13,19}$/', $number) !== 1) {
            return false;
        }

        $sum    = 0;
        $double = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }
}
