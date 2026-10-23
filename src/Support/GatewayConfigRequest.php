<?php

namespace PHPay\Support;

use PHPay\Exceptions\ValidationException;

/**
 * validates the config array before a gateway is built from it.
 *
 * the point is that a mistake here is caught at construction, with a message
 * naming the key, instead of surfacing later as a 401 from the gateway or as
 * an environment nobody meant to be in.
 */
final class GatewayConfigRequest
{
    /**
     * the config has every credential that gateway needs, and nothing it does
     * not accept.
     *
     * unknown keys are refused rather than ignored, for the same reason the
     * client factory describes a closed shape: a silently dropped `skandbox`
     * is a typo that only shows up in production.
     *
     * @param string $gateway name used in the exception message
     * @param array<string, mixed> $config
     * @param array<int, string> $credentials keys that gateway requires
     * @param array<int, string> $optional keys it accepts but does not require
     * @return void
     * @throws ValidationException
     */
    public static function validate(string $gateway, array $config, array $credentials, array $optional = []): void
    {
        $accepted = array_merge($credentials, $optional);

        foreach ($credentials as $key) {
            $value = $config[$key] ?? null;

            if (!is_string($value) || trim($value) === '') {
                throw ValidationException::make($gateway, sprintf(
                    self::messages()->missing,
                    $key,
                    implode(', ', $accepted)
                ));
            }
        }

        foreach (array_keys($config) as $key) {
            if (in_array($key, $accepted, true)) {
                continue;
            }

            throw ValidationException::make($gateway, sprintf(
                $key === 'sandbox' ? self::messages()->sandbox : self::messages()->unknown,
                $key,
                implode(', ', $accepted)
            ));
        }
    }

    /**
     * the name does not match any gateway the library ships.
     *
     * @param string $name
     * @param array<int, string> $available
     * @return ValidationException
     */
    public static function unknownGateway(string $name, array $available): ValidationException
    {
        return ValidationException::make('PHPay', sprintf(
            self::messages()->gateway,
            $name,
            implode(', ', $available)
        ));
    }

    /**
     * messages of this request.
     *
     * @return object{missing: string, unknown: string, sandbox: string, gateway: string}
     */
    public static function messages(): object
    {
        return (object) [
            'missing' => 'Informe a chave "%s" na configuração, como string não vazia. Chaves aceitas: %s.',
            'unknown' => 'A chave "%s" não é aceita na configuração deste gateway. Chaves aceitas: %s.',
            'sandbox' => 'A chave "%s" não é aceita neste gateway: o ambiente dele vem do prefixo da credencial, não de um parâmetro. Chaves aceitas: %s.',
            'gateway' => 'Não existe gateway "%s". Disponíveis: %s.',
        ];
    }
}
