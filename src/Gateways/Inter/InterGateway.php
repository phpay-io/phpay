<?php

namespace PHPay\Inter;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\{Certificate, MasksCredentials};
use PHPay\Inter\Interface\InterGatewayInterface;
use PHPay\Inter\Requests\InterWebhookEventRequest;
use PHPay\Inter\Resources\Authorization\Authorization;
use PHPay\Inter\Resources\Charge\Charge;
use PHPay\Inter\Resources\Webhook\Webhook;
use PHPay\Webhooks\Event;

/**
 * Banco Inter — the first bank here, and the first gateway whose token is
 * issued per scope.
 *
 * everything goes over mTLS, the token request included, as the BACEN standard
 * for Pix APIs requires. Inter issues the certificate and the private key as
 * two files, so `Certificate::pair()` is the way in.
 */
class InterGateway implements InterGatewayInterface
{
    /**
     * keeps the credentials out of dumps
     */
    use MasksCredentials;

    /**
     * the scopes this gateway's capabilities need, space separated.
     *
     * `scope` is required on the token request — there is no token that simply
     * carries every permission — so the union of what the resources call is
     * asked for once. Only what is actually used is listed: `lotecobv.*` and
     * `payloadlocation.*` would be refused for an application that was never
     * granted them, and nothing here calls those endpoints.
     *
     * an application with narrower permissions passes its own set to the
     * constructor.
     */
    public const SCOPES = 'cob.write cob.read cobv.write cobv.read pix.read pix.write webhook.write webhook.read';

    /**
     * seconds subtracted from the advertised lifetime, so a token never
     * expires between being handed out and being used
     */
    private const EXPIRY_MARGIN = 30;

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
     * client certificate — required on every request
     */
    private ?Certificate $certificate;

    /**
     * scopes the token is asked for
     */
    private string $scopes;

    /**
     * construct
     *
     * no network call happens here — the token is fetched lazily on first use.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param bool $sandbox the sandbox is a different domain, not a different path
     * @param Certificate|string|null $certificate the application's certificate, or
     *                                             the path to a single `.p12`/`.pem`.
     *                                             Inter issues two files: use
     *                                             Certificate::pair()
     * @param string|null $scopes space-separated; defaults to self::SCOPES
     * @param string|null $account `x-conta-corrente`, only when the application is
     *                             tied to more than one account
     * @param Client|null $client injected http client, mainly for tests
     * @throws ValidationException when the certificate path is not readable
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $clientId,
        #[\SensitiveParameter]
        private string $clientSecret,
        private bool $sandbox = true,
        Certificate|string|null $certificate = null,
        ?string $scopes = null,
        private ?string $account = null,
        private ?Client $client = null,
    ) {
        $this->certificate = is_string($certificate) ? new Certificate($certificate) : $certificate;
        $this->scopes      = $scopes !== null && trim($scopes) !== '' ? trim($scopes) : self::SCOPES;
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Inter';
    }

    /**
     * get token, authorizing on first use and again once it expires.
     *
     * **the cache is not an optimization here.** Generating a token is limited
     * to five calls per minute, and Inter's own documentation says to reuse one
     * for its full hour — a process asking per request would be refused with a
     * `429` on the sixth.
     *
     * @return array<string, mixed> token
     * @throws ValidationException|ApiException
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
     * Pix charges — immediate (`cob`) or with due date (`cobv`).
     *
     * @return Charge
     * @throws ValidationException|ApiException
     */
    public function charge(): Charge
    {
        return new Charge($this->getToken(), $this->certificate, $this->sandbox, $this->account, $this->client);
    }

    /**
     * webhooks — one per Pix key.
     *
     * @param array<mixed> $webhook `chave` and `webhookUrl`
     * @return Webhook
     * @throws ValidationException|ApiException
     */
    public function webhook(array $webhook = []): Webhook
    {
        return new Webhook(
            $this->getToken(),
            $webhook,
            $this->certificate,
            $this->sandbox,
            $this->account,
            $this->client
        );
    }

    /**
     * read a webhook notification into events.
     *
     * returns a list, never a single event — see SupportsWebhookEvents. The
     * Inter body is a bare array, not the `{"pix": [...]}` of the standard.
     *
     * @param string|array<mixed> $payload the raw body, or it already decoded
     * @return array<int, Event>
     * @throws ValidationException
     */
    public function webhookEvents(string|array $payload): array
    {
        return InterWebhookEventRequest::parse($payload);
    }

    /**
     * the scopes the token is asked for.
     *
     * @return string
     */
    public function scopes(): string
    {
        return $this->scopes;
    }

    /**
     * whether this instance talks to the sandbox.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed>
     * @throws ValidationException|ApiException
     */
    private function authorize(): array
    {
        $token = (new Authorization(
            $this->clientId,
            $this->clientSecret,
            $this->scopes,
            $this->certificate,
            $this->sandbox,
            $this->client
        ))->getToken();

        if (!isset($token['access_token']) || !isset($token['token_type'])) {
            throw new ApiException(
                'Inter: autorização não retornou access_token.',
                'Inter',
                0,
                self::withoutCredentials($token)
            );
        }

        return $token;
    }

    /**
     * the token response without the credentials it may carry.
     *
     * a response that is incomplete — say, an access_token without its
     * token_type — still holds a live token, and the exception built from it
     * ends up in logs.
     *
     * @param array<string, mixed> $token
     * @return array<string, mixed>
     */
    private static function withoutCredentials(array $token): array
    {
        return array_diff_key($token, array_flip(['access_token', 'refresh_token']));
    }

    /**
     * unix time at which a token stops being reused.
     *
     * without `expires_in` the token is kept for the life of the instance.
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
