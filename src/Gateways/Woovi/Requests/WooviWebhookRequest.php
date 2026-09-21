<?php

namespace PHPay\Woovi\Requests;

use PHPay\Exceptions\ValidationException;

class WooviWebhookRequest
{
    /**
     * validate webhook payload before sending it to the gateway.
     *
     * @param array<mixed> $webhook
     * @return void
     * @throws ValidationException
     */
    public static function validate(array $webhook): void
    {
        $messages = self::messages();

        if (!isset($webhook['name']) || !is_string($webhook['name']) || trim($webhook['name']) === '') {
            throw ValidationException::make('Woovi', $messages->name);
        }

        if (!isset($webhook['url'])
            || !is_string($webhook['url'])
            || filter_var($webhook['url'], FILTER_VALIDATE_URL) === false
        ) {
            throw ValidationException::make('Woovi', $messages->url);
        }
    }

    /**
     * messages for validation
     *
     * @return object{name: string, url: string}
     */
    public static function messages(): object
    {
        return (object) [
            'name' => 'O campo name é obrigatório — serve para você identificar o webhook.',
            'url'  => 'O campo url é obrigatório e deve ser uma URL válida.',
        ];
    }
}
