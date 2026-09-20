<?php

namespace PHPay\MercadoPago\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\MercadoPago\Requests\MercadoPagoChargeRequest;
use PHPay\MercadoPago\Resources\Charge\Interface\ChargeInterface;
use PHPay\MercadoPago\Traits\HasMercadoPagoClient;

class Charge implements ChargeInterface
{
    /**
     * trait mercado pago client
     */
    use HasMercadoPagoClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $charge = [];

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * idempotency key used on create
     */
    private ?string $idempotencyKey = null;

    /**
     * construct
     *
     * @param string $accessToken
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $accessToken,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientMercadoPagoBoot();
    }

    /**
     * set charge payload
     *
     * @param array<mixed> $charge
     * @return ChargeInterface
     */
    public function setCharge(array $charge): ChargeInterface
    {
        $this->charge = $charge;

        return $this;
    }

    /**
     * set the payer of the charge.
     *
     * Mercado Pago carries the payer inside the payment — there is no need to
     * create a customer first, unlike Asaas.
     *
     * @param array<mixed> $payer
     * @return ChargeInterface
     */
    public function setPayer(array $payer): ChargeInterface
    {
        $this->charge['payer'] = $payer;

        return $this;
    }

    /**
     * set the idempotency key used on create.
     *
     * when not set, a random key is generated per create() call. pass your own
     * to make a retry of the same business operation safe.
     *
     * @param string $key
     * @return ChargeInterface
     */
    public function setIdempotencyKey(string $key): ChargeInterface
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * set search query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * create charge
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post
     */
    public function create(): array
    {
        MercadoPagoChargeRequest::validate($this->charge);

        return $this->post('v1/payments', $this->charge, [
            'X-Idempotency-Key' => $this->idempotencyKey ?? $this->idempotencyKey(),
        ]);
    }

    /**
     * find charge by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("v1/payments/{$id}");
    }

    /**
     * search charges
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('v1/payments/search', $this->queryParams);
    }

    /**
     * get charge status
     *
     * @param string $id
     * @return string|null
     * @throws ApiException
     */
    public function getStatus(string $id): ?string
    {
        $charge = $this->find($id);

        return isset($charge['status']) && is_string($charge['status'])
            ? $charge['status']
            : null;
    }

    /**
     * get the pix copy-and-paste code of a charge
     *
     * @param string $id
     * @return string|null
     * @throws ApiException
     */
    public function getPixCode(string $id): ?string
    {
        $charge = $this->find($id);

        $interaction = $charge['point_of_interaction'] ?? null;

        if (!is_array($interaction)) {
            return null;
        }

        $transaction = $interaction['transaction_data'] ?? null;

        if (!is_array($transaction)) {
            return null;
        }

        $code = $transaction['qr_code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * cancel charge
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $id): array
    {
        return $this->put("v1/payments/{$id}", ['status' => 'cancelled']);
    }

    /**
     * refund charge, fully or partially
     *
     * @param string $id
     * @param float|null $value null refunds the full amount
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $id, ?float $value = null): array
    {
        return $this->post(
            "v1/payments/{$id}/refunds",
            $value === null ? [] : ['amount' => $value],
            ['X-Idempotency-Key' => $this->idempotencyKey ?? $this->idempotencyKey()]
        );
    }
}
