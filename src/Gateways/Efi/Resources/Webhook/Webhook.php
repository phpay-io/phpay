<?php

namespace PHPay\Efi\Resources\Webhook;

use GuzzleHttp\Client;
use PHPay\Efi\Requests\EfiWebhookRequest;
use PHPay\Efi\Resources\Webhook\Interface\WebhookInterface;
use PHPay\Efi\Traits\HasEfiPixClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;

/**
 * webhooks of the Efí Pix API.
 *
 * one webhook per Pix key, addressed by the key itself — there is no webhook
 * id. and the mTLS goes both ways: by default Efí only delivers to a server
 * that validates the Efí certificate. when yours cannot (shared hosting, a
 * load balancer that terminates TLS), skipMtlsChecking() turns that off —
 * then validate the origin some other way, like an hmac in the URL.
 */
class Webhook implements WebhookInterface
{
    /**
     * trait Efí Pix client
     */
    use HasEfiPixClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * whether Efí skips the mTLS check on the webhook server
     */
    private bool $skipMtlsChecking = false;

    /**
     * construct
     *
     * @param array<string, mixed> $token
     * @param array<mixed> $webhook `chave` and `webhookUrl`
     * @param Certificate|null $certificate required unless a client is injected
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     * @throws ValidationException
     */
    public function __construct(
        array $token,
        private array $webhook = [],
        ?Certificate $certificate = null,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientEfiPixBoot($token, $certificate);
    }

    /**
     * configure the webhook of a Pix key.
     *
     * configuring again replaces the URL: there is one webhook per key.
     *
     * @param array<mixed> $webhook overrides the payload given to the gateway
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(array $webhook = []): array
    {
        if (!empty($webhook)) {
            $this->webhook = $webhook;
        }

        $webhook = $this->webhook;

        EfiWebhookRequest::validate($webhook);

        return $this->put(
            'v2/webhook/' . rawurlencode($webhook['chave']),
            ['webhookUrl' => $webhook['webhookUrl']],
            $this->skipMtlsChecking ? ['x-skip-mtls-checking' => 'true'] : [],
        );
    }

    /**
     * skip the mTLS check Efí makes on your server before delivering.
     *
     * @param bool $skip
     * @return WebhookInterface
     */
    public function skipMtlsChecking(bool $skip = true): WebhookInterface
    {
        $this->skipMtlsChecking = $skip;

        return $this;
    }

    /**
     * find the webhook of a Pix key
     *
     * @param string $key
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $key): array
    {
        return $this->get('v2/webhook/' . rawurlencode($key));
    }

    /**
     * list webhooks. `inicio` and `fim` default to the last 30 days.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('v2/webhook', $this->queryParams + [
            'inicio' => gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days')),
            'fim'    => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * remove the webhook of a Pix key
     *
     * @param string $key
     * @return bool
     * @throws ApiException
     */
    public function destroy(string $key): bool
    {
        return $this->delete('v2/webhook/' . rawurlencode($key));
    }

    /**
     * set list filters
     *
     * @param array<mixed> $queryParams
     * @return WebhookInterface
     */
    public function setQueryParams(array $queryParams): WebhookInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }
}
