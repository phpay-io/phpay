<?php

namespace PHPay\Inter\Requests;

use PHPay\Exceptions\ValidationException;

class InterWebhookRequest
{
    /**
     * validate webhook payload before sending it to the gateway.
     *
     * @param array<mixed> $webhook
     * @return void
     * @throws ValidationException
     * @phpstan-assert array{chave: string, webhookUrl: string} $webhook
     */
    public static function validate(array $webhook): void
    {
        $messages = self::messages();

        if (!isset($webhook['chave']) || !is_string($webhook['chave']) || trim($webhook['chave']) === '') {
            throw ValidationException::make('Inter', $messages->key);
        }

        $url = $webhook['webhookUrl'] ?? null;

        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw ValidationException::make('Inter', $messages->url);
        }

        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw ValidationException::make('Inter', $messages->https);
        }
    }

    /**
     * messages for validation
     *
     * @return object{key: string, url: string, https: string}
     */
    public static function messages(): object
    {
        return (object) [
            'key'   => 'O campo chave é obrigatório: no Inter o webhook é configurado por chave Pix.',
            'url'   => 'O campo webhookUrl é obrigatório e deve ser uma URL válida.',
            'https' => 'O Inter só aceita webhook em URL https — o schema dele é ^https://[^\\s]*$.',
        ];
    }
}
