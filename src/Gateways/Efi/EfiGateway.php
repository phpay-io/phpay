<?php

namespace PHPay\Efi;

use GuzzleHttp\Client;
use PHPay\Efi\Interface\EfiGatewayInterface;
use PHPay\Efi\Resources\Authorization\Authorization;
use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Efi\Resources\Pix\Pix;
use PHPay\Efi\Resources\PixAuthorization\PixAuthorization;
use PHPay\Efi\Resources\PixCharge\PixCharge;
use PHPay\Efi\Resources\Subscription\Subscription;
use PHPay\Efi\Resources\Webhook\Webhook;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;

class EfiGateway implements EfiGatewayInterface
{
    /**
     * credentials exchanged for an access token
     *
     * @var array<string, mixed>|null
     */
    private ?array $token = null;

    /**
     * unix time after which the token is considered stale
     */
    private int $tokenExpiresAt = 0;

    /**
     * seconds subtracted from the advertised lifetime, so a token never
     * expires between being handed out and being used
     */
    private const EXPIRY_MARGIN = 30;

    /**
     * token of the Pix API — a different one from the Cobranças token
     *
     * @var array<string, mixed>|null
     */
    private ?array $pixToken = null;

    /**
     * unix time after which the Pix token is considered stale
     */
    private int $pixTokenExpiresAt = 0;

    /**
     * client certificate of the Pix API
     */
    private ?Certificate $certificate;

    /**
     * construct
     *
     * no network call happens here — each token is fetched lazily on first use.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param bool $sandbox
     * @param Client|null $client injected http client of the Cobranças API, mainly for tests
     * @param Certificate|string|null $certificate .p12/.pem of the application, or its path —
     *                                             required by the Pix API only
     * @param Client|null $pixClient injected http client of the Pix API, mainly for tests
     * @throws ValidationException when the certificate path is not a readable .p12/.pem
     */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $sandbox = true,
        private ?Client $client = null,
        Certificate|string|null $certificate = null,
        private ?Client $pixClient = null,
    ) {
        $this->certificate = is_string($certificate) ? new Certificate($certificate) : $certificate;
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Efí';
    }

    /**
     * get token, authorizing on first use and again once it expires.
     *
     * the gateway may outlive the token — a queue worker keeps the same
     * instance for hours — so the lifetime Efí advertises is honored.
     *
     * @return array<string, mixed> token
     * @throws ApiException
     */
    public function getToken(): array
    {
        if ($this->token === null || time() >= $this->tokenExpiresAt) {
            $this->token          = $this->authorize();
            $this->tokenExpiresAt = self::expiresAt($this->token);
        }

        return $this->token;
    }

    /**
     * get token of the Pix API, authorizing on first use and again once it
     * expires.
     *
     * @return array<string, mixed> token
     * @throws ValidationException|ApiException
     */
    public function getPixToken(): array
    {
        if ($this->pixToken === null || time() >= $this->pixTokenExpiresAt) {
            $this->pixToken          = $this->authorizePix();
            $this->pixTokenExpiresAt = self::expiresAt($this->pixToken);
        }

        return $this->pixToken;
    }

    /**
     * create charge — a boleto, in the Cobranças API.
     *
     * @param array<mixed> $charge
     * @return Charge
     * @throws ApiException
     */
    public function charge(array $charge = []): Charge
    {
        return new Charge(
            $this->getToken(),
            $charge,
            $this->sandbox,
            $this->client
        );
    }

    /**
     * Pix charges — immediate or with due date, in the Pix API.
     *
     * an extra of the concrete gateway, like webhookDeliveries() of Pagar.me:
     * charge() is already the boleto, and changing what it returns would
     * break every integration on v2.
     *
     * @return PixCharge
     * @throws ValidationException|ApiException
     */
    public function pixCharge(): PixCharge
    {
        return new PixCharge($this->getPixToken(), $this->certificate, $this->sandbox, $this->pixClient);
    }

    /**
     * webhooks of the Pix API — one per Pix key.
     *
     * @param array<mixed> $webhook `chave` and `webhookUrl`
     * @return Webhook
     * @throws ValidationException|ApiException
     */
    public function webhook(array $webhook = []): Webhook
    {
        return new Webhook($this->getPixToken(), $webhook, $this->certificate, $this->sandbox, $this->pixClient);
    }

    /**
     * Pix keys — random keys (EVP).
     *
     * @return Pix
     * @throws ValidationException|ApiException
     */
    public function pix(): Pix
    {
        return new Pix($this->getPixToken(), $this->certificate, $this->sandbox, $this->pixClient);
    }

    /**
     * Pix Automático — recurring debit authorized once by the payer.
     *
     * @return Subscription
     * @throws ValidationException|ApiException
     */
    public function subscription(): Subscription
    {
        return new Subscription($this->getPixToken(), $this->certificate, $this->sandbox, $this->pixClient);
    }

    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    private function authorize(): array
    {
        $token = (new Authorization(
            $this->clientId,
            $this->clientSecret,
            $this->sandbox,
            $this->client
        ))->getToken();

        if (!isset($token['access_token']) || !isset($token['token_type'])) {
            throw new ApiException(
                'Efí: autorização não retornou access_token.',
                'Efí',
                0,
                $token
            );
        }

        return $token;
    }

    /**
     * exchange credentials for an access token of the Pix API.
     *
     * @return array<string, mixed>
     * @throws ValidationException|ApiException
     */
    private function authorizePix(): array
    {
        $token = (new PixAuthorization(
            $this->clientId,
            $this->clientSecret,
            $this->certificate,
            $this->sandbox,
            $this->pixClient
        ))->getToken();

        if (!isset($token['access_token']) || !isset($token['token_type'])) {
            throw new ApiException(
                'Efí: autorização da API Pix não retornou access_token.',
                'Efí',
                0,
                $token
            );
        }

        return $token;
    }

    /**
     * unix time at which a token stops being reused.
     *
     * without `expires_in` the token is kept for the life of the instance,
     * which is what the gateway always did.
     *
     * @param array<string, mixed> $token
     * @return int
     */
    private static function expiresAt(array $token): int
    {
        $lifetime = $token['expires_in'] ?? null;

        return is_numeric($lifetime)
            ? time() + (int) $lifetime - self::EXPIRY_MARGIN
            : PHP_INT_MAX;
    }
}
