<?php

namespace PHPay\Inter\Traits;

use GuzzleHttp\Client;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\{Certificate, ClientFactory, HasHttpClient};

/**
 * http client of the Inter Pix API.
 *
 * one host for everything — the OAuth path and the Pix paths live under the
 * same domain, unlike Rede — and **every** request goes over mTLS, the token
 * one included, as the BACEN standard requires.
 */
trait HasInterClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * client used to exchange credentials for an access token.
     *
     * the token endpoint is form-encoded, not JSON, and the credentials travel
     * in the body rather than in a Basic header — which is why this client
     * carries no Authorization at all.
     *
     * @param Certificate|null $certificate
     * @return Client
     * @throws ValidationException
     */
    protected function clientInterAuthorize(?Certificate $certificate): Client
    {
        return ClientFactory::make([
            'base_uri' => $this->interBaseUri(),
            'headers'  => [
                'content-type' => 'application/x-www-form-urlencoded',
            ],
        ] + $this->requireCertificate($certificate)->guzzleOptions());
    }

    /**
     * boot client
     *
     * @param array<string, mixed> $token
     * @param Certificate|null $certificate
     * @param string|null $account `x-conta-corrente`, only when the application
     *                             is tied to more than one account
     * @return Client
     * @throws ValidationException
     */
    protected function clientInterBoot(
        #[\SensitiveParameter]
        array $token,
        ?Certificate $certificate,
        ?string $account = null
    ): Client {
        $accessToken = $token['access_token'] ?? null;
        $tokenType   = $token['token_type'] ?? null;

        if (!is_string($accessToken) || !is_string($tokenType)) {
            throw ValidationException::make(
                'Inter',
                'Token inválido: access_token e token_type devem ser strings.'
            );
        }

        $headers = [
            'Authorization' => "{$tokenType} {$accessToken}",
            'content-type'  => 'application/json',
        ];

        /*
         * only sent when there is one: the header identifies which account to
         * operate on, and Inter requires it only for an application tied to
         * more than one. Sending it always would force every integration to
         * know an account number it does not need.
         */
        if ($account !== null && trim($account) !== '') {
            $headers['x-conta-corrente'] = $account;
        }

        return ClientFactory::make([
            'base_uri' => $this->interBaseUri(),
            'headers'  => $headers,
        ] + $this->requireCertificate($certificate)->guzzleOptions());
    }

    /**
     * base uri for the current environment.
     *
     * the sandbox lives on a different domain entirely — `uatinter.co`, not
     * `bancointer.com.br` — like the Woovi one.
     *
     * @return string
     */
    protected function interBaseUri(): string
    {
        return $this->sandbox
            ? 'https://cdpj-sandbox.partners.uatinter.co/'
            : 'https://cdpj.partners.bancointer.com.br/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Inter';
    }

    /**
     * the certificate, or a clear error instead of a TLS handshake failure.
     *
     * @param Certificate|null $certificate
     * @return Certificate
     * @throws ValidationException
     */
    private function requireCertificate(?Certificate $certificate): Certificate
    {
        if ($certificate === null) {
            throw ValidationException::make(
                'Inter',
                'a API do Inter exige o certificado da aplicação em toda requisição (mTLS), '
                . 'inclusive a do token. O Inter entrega certificado e chave separados: '
                . 'new InterGateway($id, $secret, certificate: Certificate::pair(\'/caminho/certificado.crt\', \'/caminho/chave.key\')).'
            );
        }

        return $certificate;
    }
}
