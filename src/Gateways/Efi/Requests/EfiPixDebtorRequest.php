<?php

namespace PHPay\Efi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Support\Customer;

/**
 * the debtor (`devedor`) of the Pix API — BACEN field names, not the ones of
 * the Cobranças API: `cpf` or `cnpj` apart, and `nome` instead of `name`.
 */
class EfiPixDebtorRequest
{
    /**
     * validate debtor payload before sending it to the gateway.
     *
     * @param array<mixed> $debtor
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $debtor): void
    {
        $messages = self::messages();

        if (!isset($debtor['nome']) || !is_string($debtor['nome']) || trim($debtor['nome']) === '') {
            throw ValidationException::make('Efí', $messages->name);
        }

        $cpf  = $debtor['cpf'] ?? null;
        $cnpj = $debtor['cnpj'] ?? null;

        if (($cpf === null) === ($cnpj === null)) {
            throw ValidationException::make('Efí', $messages->document);
        }

        if ($cpf !== null && (!is_string($cpf) || preg_match('/^\d{11}$/', $cpf) !== 1)) {
            throw ValidationException::make('Efí', $messages->cpf);
        }

        if ($cnpj !== null && (!is_string($cnpj) || preg_match('/^[0-9A-Z]{14}$/', $cnpj) !== 1)) {
            throw ValidationException::make('Efí', $messages->cnpj);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, document: string, cpf: string, cnpj: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name'     => 'O devedor precisa de nome.',
            'document' => 'O devedor precisa de cpf ou de cnpj — um dos dois, nunca os dois.',
            'cpf'      => 'O cpf do devedor deve ter 11 dígitos, somente números.',
            'cnpj'     => 'O cnpj do devedor deve ter 14 caracteres, sem pontuação.',
        ];
    }

    /**
     * map the library's Customer onto the debtor the Pix API expects.
     *
     * @param Customer $customer
     * @return array<string, string>
     */
    public static function fromCustomer(Customer $customer): array
    {
        $debtor = ['nome' => $customer->name];

        if ($customer->document !== null) {
            $debtor[$customer->isCompany() ? 'cnpj' : 'cpf'] = $customer->document;
        }

        return $debtor;
    }
}
