<?php

namespace PHPay\Http;

use GuzzleHttp\Client;

/**
 * keeps credentials out of var_dump(), print_r() and Xdebug.
 *
 * gateways and resources hold the token they were built with, and the http
 * client carries it again in its default headers. Dumping either one while
 * debugging would print the credential; this masks both and leaves the rest
 * — payloads, filters, the environment — visible, because that is what one
 * dumps an object to see.
 *
 * Symfony's VarDumper (dd(), dump()) reads private properties on its own and
 * only adds what __debugInfo() returns, so it is not covered here.
 */
trait MasksCredentials
{
    /**
     * the object as a dump should show it.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $visible = [];

        foreach (get_object_vars($this) as $name => $value) {
            $visible[$name] = match (true) {
                $value === null                                     => null,
                $value instanceof Client                            => '[oculto: o client HTTP carrega a credencial]',
                in_array($name, self::credentialProperties(), true) => '********',
                default                                             => $value,
            };
        }

        return $visible;
    }

    /**
     * property names that hold a credential, in any gateway.
     *
     * a method, not a constant: constants inside traits only exist from
     * PHP 8.2 and this library supports 8.1.
     *
     * @return array<int, string>
     */
    private static function credentialProperties(): array
    {
        return [
            'token',
            'pixToken',
            'accessToken',
            'secretKey',
            'secret',
            'clientId',
            'clientSecret',
            'merchantKey',
            'appId',
        ];
    }
}
