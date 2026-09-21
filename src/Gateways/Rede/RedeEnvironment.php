<?php

namespace PHPay\Rede;

/**
 * hosts and paths of e.Rede, which differ in more than the domain: the token
 * endpoint itself sits at a different path in each environment.
 *
 * kept apart from the client trait so the gateway can read the environment
 * without pulling in the http verbs it does not use.
 */
final class RedeEnvironment
{
    /**
     * base uri of the transactions API
     *
     * @param bool $sandbox
     * @return string
     */
    public static function api(bool $sandbox): string
    {
        return $sandbox
            ? 'https://sandbox-erede.useredecloud.com.br/v2/'
            : 'https://api.userede.com.br/erede/v2/';
    }

    /**
     * base uri of the authorization host
     *
     * @param bool $sandbox
     * @return string
     */
    public static function oauth(bool $sandbox): string
    {
        return $sandbox
            ? 'https://rl7-sandbox-api.useredecloud.com.br/'
            : 'https://api.userede.com.br/';
    }

    /**
     * path of the token endpoint
     *
     * @param bool $sandbox
     * @return string
     */
    public static function tokenPath(bool $sandbox): string
    {
        return $sandbox ? 'oauth2/token' : 'redelabs/oauth2/token';
    }
}
