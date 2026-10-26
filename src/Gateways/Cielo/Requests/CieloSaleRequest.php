<?php

namespace PHPay\Cielo\Requests;

use PHPay\Cielo\Enums\PaymentTypeEnum;
use PHPay\Exceptions\ValidationException;
use PHPay\Support\{Card, ChargeIntent};
use PHPay\Support\Customer;

class CieloSaleRequest
{
    /**
     * the Cielo envelope for a portable charge description.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     */
    public static function fromIntent(ChargeIntent $intent): array
    {
        if ($intent->dueDate !== null) {
            throw ValidationException::make('Cielo', self::messages()->dueDate);
        }

        $sale = $intent->reference !== null ? ['MerchantOrderId' => $intent->reference] : [];

        return $sale + $intent->extra();
    }

    /**
     * the Cielo shape for the card itself.
     *
     * the brand is required and is NOT guessed from the number: a wrong
     * brand is refused by the Cielo, and deriving it from the BIN range
     * would be the library inventing a fact about the card.
     *
     * @param Card $card
     * @return array<string, string>
     * @throws ValidationException
     */
    public static function cardFromIntent(Card $card): array
    {
        if ($card->brand === null || trim($card->brand) === '') {
            throw ValidationException::make('Cielo', self::messages()->brand);
        }

        return [
            'CardNumber'     => $card->number,
            'Holder'         => $card->holderName,
            'ExpirationDate' => $card->expiration(),
            'SecurityCode'   => $card->securityCode,
            'Brand'          => $card->brand,
        ];
    }

    /**
     * validate sale payload before sending it to the gateway.
     *
     * @param array<mixed> $sale
     * @return void
     * @throws ValidationException
     * @see https://developercielo.github.io/manual/cielo-ecommerce
     */
    public static function validate(array $sale): void
    {
        $messages = self::messages();

        if (!isset($sale['MerchantOrderId'])
            || !is_string($sale['MerchantOrderId'])
            || trim($sale['MerchantOrderId']) === ''
        ) {
            throw ValidationException::make('Cielo', $messages->merchantOrderId);
        }

        if (!isset($sale['Customer']) || !is_array($sale['Customer'])) {
            throw ValidationException::make('Cielo', $messages->customer);
        }

        if (!isset($sale['Customer']['Name'])
            || !is_string($sale['Customer']['Name'])
            || trim($sale['Customer']['Name']) === ''
        ) {
            throw ValidationException::make('Cielo', $messages->customerName);
        }

        if (!isset($sale['Payment']) || !is_array($sale['Payment'])) {
            throw ValidationException::make('Cielo', $messages->payment);
        }

        $payment = $sale['Payment'];

        if (!isset($payment['Type'])
            || !is_string($payment['Type'])
            || !PaymentTypeEnum::tryFrom($payment['Type']) instanceof PaymentTypeEnum
        ) {
            throw ValidationException::make('Cielo', $messages->paymentType);
        }

        if (!isset($payment['Amount']) || !is_int($payment['Amount']) || $payment['Amount'] < 1) {
            throw ValidationException::make('Cielo', $messages->amount);
        }
    }

    /**
     * messages for validation
     *
     * @return object{merchantOrderId: string, customer: string, customerName: string, payment: string, paymentType: string, amount: string, brand: string, dueDate: string}
     */
    public static function messages(): object
    {
        return (object) [
            'merchantOrderId' => 'O campo MerchantOrderId é obrigatório — é o identificador do pedido no seu sistema.',
            'customer'        => 'O campo Customer é obrigatório e deve ser um array. Use setCustomer().',
            'customerName'    => 'O campo Customer.Name é obrigatório e deve ser uma string não vazia.',
            'payment'         => 'O campo Payment é obrigatório. Use setPix(), setBoleto() ou setCreditCard().',
            'paymentType'     => 'O campo Payment.Type é obrigatório e aceita apenas: CreditCard, DebitCard, Pix, Boleto.',
            'amount'          => 'O campo Payment.Amount é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. A Cielo não aceita valor decimal: R$ 157,00 é 15700.',
            'dueDate'         => 'O Payment do Pix da Cielo não tem onde levar vencimento, e o campo de vencimento do boleto dela não está confirmado — engolir a data em silêncio cobraria com prazo que ninguém escolheu. Para boleto com vencimento, use charge()->setBoleto($valor, $options) com o campo que a documentação da Cielo nomear.',
            'brand'           => 'A Cielo exige a bandeira do cartão (Visa, Master, Elo…). Informe-a no Card: a biblioteca não a adivinha pela faixa do número, porque isso seria inventar um fato sobre o cartão.',
        ];
    }

    /**
     * map the library's Customer onto the payload Cielo expects.
     *
     * Cielo is the only gateway that capitalises the keys.
     *
     * @param Customer $customer
     * @return array<mixed>
     */
    public static function fromCustomer(Customer $customer): array
    {
        return array_filter([
            'Name'         => $customer->name,
            'Email'        => $customer->email,
            'Identity'     => $customer->document,
            'IdentityType' => $customer->documentType(),
        ], fn ($value) => $value !== null) + $customer->extra();
    }

}
