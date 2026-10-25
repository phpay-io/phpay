<?php

namespace PHPay\Inter\Resources\Webhook;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;
use PHPay\Inter\Requests\InterWebhookRequest;
use PHPay\Inter\Resources\Webhook\Interface\WebhookInterface;
use PHPay\Inter\Traits\HasInterClient;

/**
 * webhooks of the Inter Pix API.
 *
 * one webhook per Pix key, addressed by the key itself — there is no webhook
 * id, the same shape as Efí.
 *
 * the URL is sent exactly as the caller passed it, and nothing here rewrites
 * it. That is worth stating because the reference invites the opposite
 * reading: Redoc renders the callback as
 * `POST {$request.body#/webhookUrl/pix}`, which looks like `/pix` being
 * appended to the registered URL. It is not. `#/` introduces a JSON Pointer,
 * and that one would point at a `pix` property inside a string; what happened
 * is that the callback is **named** `pix` in the spec and Redoc joined the
 * name to the expression. Inter's own text describes a `POST` to the
 * registered `webhookUrl`, and its example carries no suffix. The `/pix`
 * suffix is a documented quirk of Itaú, which implements the same
 * specification.
 *
 * there is no list-all: Inter exposes the key endpoints and the callback log,
 * and no `GET /webhook`. The error taxonomy in the reference does mention one,
 * but that section is the BACEN text copied whole and names endpoints this
 * gateway does not serve.
 */
class Webhook implements WebhookInterface
{
    /**
     * trait Inter client
     */
    use HasInterClient;
    /**
     * path prefix of the Pix API, under the single Inter host
     */
    private const PIX = 'pix/v2/';

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * construct
     *
     * @param array<string, mixed> $token
     * @param array<mixed> $webhook `chave` and `webhookUrl`
     * @param Certificate|null $certificate required unless a client is injected
     * @param bool $sandbox
     * @param string|null $account `x-conta-corrente`
     * @param Client|null $client injected http client, mainly for tests
     * @throws ValidationException
     */
    public function __construct(
        #[\SensitiveParameter]
        array $token,
        private array $webhook = [],
        ?Certificate $certificate = null,
        protected bool $sandbox = true,
        ?string $account = null,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientInterBoot($token, $certificate, $account);
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

        InterWebhookRequest::validate($webhook);

        return $this->put(
            self::PIX . 'webhook/' . rawurlencode($webhook['chave']),
            ['webhookUrl' => $webhook['webhookUrl']],
        );
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
        return $this->get(self::PIX . 'webhook/' . rawurlencode($key));
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
        return $this->delete(self::PIX . 'webhook/' . rawurlencode($key));
    }

    /**
     * the callbacks Inter tried to deliver, newest first.
     *
     * an extra of the concrete resource, not a capability — it reads the
     * delivery log, the same thing `webhookDeliveries()` does on Pagar.me.
     * It is how you find out a notification was sent and your server answered
     * with a 500: each entry carries `sucesso`, `httpStatus`, `mensagemErro`
     * and `numeroTentativa`.
     *
     * the period is required by the API and defaults to the last 30 days.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function callbacks(): array
    {
        return $this->get(self::PIX . 'webhook/callbacks', $this->queryParams + [
            'dataHoraInicio' => gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days')),
            'dataHoraFim'    => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * ask Inter to deliver callbacks again.
     *
     * for when your server was down: Inter retries four times on its own, at
     * 20, 30, 60 and 120 minutes, and after that the notification is gone
     * unless it is asked for again.
     *
     * @param string $key the Pix key — the same one for every txid in the call
     * @param array<int, string> $txids up to 50
     * @return array<mixed>
     * @throws ApiException
     */
    public function retryCallbacks(string $key, array $txids): array
    {
        return $this->post(self::PIX . 'webhook/callbacks/retry', [
            'chavePix' => $key,
            'txIds'    => array_values($txids),
        ]);
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
