<?php

namespace PHPay\Rede\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Rede\Enums\TransactionKindEnum;

class RedeTransactionRequest
{
    /**
     * validate transaction payload before sending it to the gateway.
     *
     * @param array<mixed> $transaction
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $transaction): void
    {
        $messages = self::messages();

        if (!isset($transaction['reference'])
            || !is_string($transaction['reference'])
            || trim($transaction['reference']) === ''
        ) {
            throw ValidationException::make('Rede', $messages->reference);
        }

        if (!isset($transaction['amount'])
            || !is_int($transaction['amount'])
            || $transaction['amount'] < 1
        ) {
            throw ValidationException::make('Rede', $messages->amount);
        }

        if (!isset($transaction['kind'])
            || !is_string($transaction['kind'])
            || !TransactionKindEnum::tryFrom($transaction['kind']) instanceof TransactionKindEnum
        ) {
            throw ValidationException::make('Rede', $messages->kind);
        }

        if (!isset($transaction['installments'])
            || !is_int($transaction['installments'])
            || $transaction['installments'] < 1
        ) {
            throw ValidationException::make('Rede', $messages->installments);
        }

        foreach (['cardNumber', 'cardHolderName', 'expirationMonth', 'expirationYear', 'securityCode'] as $field) {
            if (!isset($transaction[$field])) {
                throw ValidationException::make('Rede', $messages->card);
            }
        }
    }

    /**
     * messages for validation
     *
     * @return object{reference: string, amount: string, kind: string, installments: string, card: string}
     */
    public static function messages(): object
    {
        return (object) [
            'reference'    => 'O campo reference é obrigatório — é o identificador do pedido no seu sistema.',
            'amount'       => 'O campo amount é obrigatório e deve ser um inteiro em CENTAVOS maior que zero. A Rede não aceita valor decimal: R$ 20,99 é 2099.',
            'kind'         => 'O campo kind é obrigatório e aceita apenas: credit, debit.',
            'installments' => 'O campo installments é obrigatório e deve ser um inteiro maior ou igual a 1.',
            'card'         => 'Os dados do cartão são obrigatórios: cardNumber, cardHolderName, expirationMonth, expirationYear e securityCode. Use setCard().',
        ];
    }
}
