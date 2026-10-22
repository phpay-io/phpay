<?php

namespace PHPay\Webhooks;

use PHPay\Exceptions\ValidationException;

/**
 * validates what a signature check needs before it checks anything.
 *
 * a verifier given an empty payload or no secret would hash nothing and
 * answer false, which reads exactly like a forged notification. These are
 * mistakes in the integration, not forgeries, so they get an exception
 * instead of a quiet no.
 */
final class SignatureRequest
{
    /**
     * the secret must be there.
     *
     * @param string $gateway name used in the exception message
     * @param string $secret shared secret, token or key
     * @return void
     * @throws ValidationException
     */
    public static function requireSecret(string $gateway, string $secret): void
    {
        if ($secret === '') {
            throw ValidationException::make($gateway, self::messages()->secret);
        }
    }

    /**
     * the raw body must be there, and must be the raw one.
     *
     * @param string $gateway
     * @param string $payload
     * @return void
     * @throws ValidationException
     */
    public static function requirePayload(string $gateway, string $payload): void
    {
        if ($payload === '') {
            throw ValidationException::make($gateway, self::messages()->payload);
        }
    }

    /**
     * both, for the schemes that sign the body.
     *
     * @param string $gateway
     * @param string $payload
     * @param string $secret
     * @return void
     * @throws ValidationException
     */
    public static function validate(string $gateway, string $payload, string $secret): void
    {
        self::requireSecret($gateway, $secret);
        self::requirePayload($gateway, $payload);
    }

    /**
     * the header the gateway signs with is missing from the request.
     *
     * @param string $gateway
     * @param string $header
     * @return ValidationException
     */
    public static function missingHeader(string $gateway, string $header): ValidationException
    {
        return ValidationException::make($gateway, sprintf(self::messages()->header, $header));
    }

    /**
     * messages of this request.
     *
     * @return object{secret: string, payload: string, header: string}
     */
    public static function messages(): object
    {
        return (object) [
            'secret'  => 'Informe o segredo do webhook com setSecret() antes de verificar a assinatura.',
            'payload' => 'Informe o corpo cru da requisição com setPayload(). Não use o JSON decodificado e reserializado: um espaço a mais muda a assinatura.',
            'header'  => 'A requisição não traz o header %s, então não há assinatura para verificar. Confira se o seu servidor repassa os headers do gateway.',
        ];
    }
}
