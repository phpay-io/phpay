<?php

namespace PHPay\Woovi\Requests;

use PHPay\Exceptions\ValidationException;
use PHPay\Woovi\Enums\PixKeyTypeEnum;

class WooviPixKeyRequest
{
    /**
     * validate Pix key payload before sending it to the gateway.
     *
     * @param array<mixed> $key
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $key): void
    {
        $messages = self::messages();

        $type = $key['type'] ?? null;

        if (!is_string($type) || !PixKeyTypeEnum::tryFrom($type) instanceof PixKeyTypeEnum) {
            throw ValidationException::make('Woovi', $messages->type);
        }

        /* a chave aleatória é gerada pelo banco, então não vem no payload */
        if ($type === PixKeyTypeEnum::RANDOM->value) {
            return;
        }

        if (!isset($key['key']) || !is_string($key['key']) || trim($key['key']) === '') {
            throw ValidationException::make('Woovi', $messages->key);
        }
    }

    /**
     * validate the payload of a static QR Code.
     *
     * @param array<mixed> $qrCode
     * @return void
     * @throws ValidationException
     */
    public static function validateStaticQrCode(array $qrCode): void
    {
        $messages = self::messages();

        if (!isset($qrCode['name']) || !is_string($qrCode['name']) || trim($qrCode['name']) === '') {
            throw ValidationException::make('Woovi', $messages->qrCodeName);
        }

        if (isset($qrCode['value']) && (!is_int($qrCode['value']) || $qrCode['value'] < 1)) {
            throw ValidationException::make('Woovi', $messages->qrCodeValue);
        }
    }

    /**
     * messages for validation
     *
     * @return object{type: string, key: string, qrCodeName: string, qrCodeValue: string}
     */
    public static function messages(): object
    {
        return (object) [
            'type'        => 'O campo type é obrigatório e aceita apenas: CPF, CNPJ, EMAIL, PHONE, EVP.',
            'key'         => 'O campo key é obrigatório para todo tipo exceto EVP, cuja chave aleatória é gerada pelo banco.',
            'qrCodeName'  => 'O QR Code estático precisa de um name para identificá-lo.',
            'qrCodeValue' => 'O campo value do QR Code, quando informado, deve ser um inteiro em CENTAVOS maior que zero. Sem ele, o pagador escolhe o valor.',
        ];
    }
}
