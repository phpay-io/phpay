<?php

namespace PHPay\Efi\Traits;

use GuzzleHttp\Client;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\{Certificate, HasHttpClient};

/**
 * http client of the Efí Pix API.
 *
 * a second API, on its own host, apart from the Cobranças one HasEfiClient
 * talks to. every request — the token one included — goes over mTLS with
 * the certificate of the application.
 */
trait HasEfiPixClient
{
    /**
     * shared http verbs
     */
    use HasHttpClient;

    /**
     * client used to exchange credentials for an access token
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param Certificate|null $certificate
     * @return Client
     * @throws ValidationException
     */
    protected function clientEfiPixAuthorize(
        string $clientId,
        string $clientSecret,
        ?Certificate $certificate
    ): Client {
        return new Client([
            'base_uri' => $this->pixBaseUri(),
            'auth'     => [$clientId, $clientSecret],
            'headers'  => [
                'content-type' => 'application/json',
            ],
        ] + $this->requireCertificate($certificate)->guzzleOptions());
    }

    /**
     * boot client
     *
     * @param array<string, mixed> $token
     * @param Certificate|null $certificate
     * @return Client
     * @throws ValidationException
     */
    protected function clientEfiPixBoot(array $token, ?Certificate $certificate): Client
    {
        $accessToken = $token['access_token'] ?? null;
        $tokenType   = $token['token_type'] ?? null;

        if (!is_string($accessToken) || !is_string($tokenType)) {
            throw ValidationException::make(
                'Efí',
                'Token inválido: access_token e token_type devem ser strings.'
            );
        }

        return new Client([
            'base_uri' => $this->pixBaseUri(),
            'headers'  => [
                'Authorization' => "{$tokenType} {$accessToken}",
                'content-type'  => 'application/json',
            ],
        ] + $this->requireCertificate($certificate)->guzzleOptions());
    }

    /**
     * base uri of the Pix API for the current environment
     *
     * @return string
     */
    protected function pixBaseUri(): string
    {
        return $this->sandbox
            ? 'https://pix-h.api.efipay.com.br/'
            : 'https://pix.api.efipay.com.br/';
    }

    /**
     * gateway name used in exception messages.
     *
     * @return string
     */
    protected function gatewayName(): string
    {
        return 'Efí';
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
                'Efí',
                'a API Pix exige o certificado .p12 ou .pem da aplicação (mTLS). '
                . 'Passe-o no construtor: new EfiGateway($id, $secret, certificate: \'/caminho/certificado.p12\').'
            );
        }

        return $certificate;
    }
}
