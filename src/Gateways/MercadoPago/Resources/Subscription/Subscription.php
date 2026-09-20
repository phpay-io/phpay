<?php

namespace PHPay\MercadoPago\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\MercadoPago\Enums\SubscriptionStatusEnum;
use PHPay\MercadoPago\Requests\MercadoPagoSubscriptionRequest;
use PHPay\MercadoPago\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\MercadoPago\Traits\HasMercadoPagoClient;

class Subscription implements SubscriptionInterface
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
     * payer e-mail of the subscription
     */
    private ?string $payerEmail = null;

    /**
     * preapproval plan the subscription belongs to
     */
    private ?string $planId = null;

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

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
     * set the payer e-mail of the subscription
     *
     * @param string $email
     * @return SubscriptionInterface
     */
    public function setPayerEmail(string $email): SubscriptionInterface
    {
        $this->payerEmail = $email;

        return $this;
    }

    /**
     * attach an existing preapproval plan.
     *
     * with a plan, the recurrence comes from the plan and auto_recurring is
     * not required on the payload.
     *
     * @param string $planId
     * @return SubscriptionInterface
     */
    public function setPlan(string $planId): SubscriptionInterface
    {
        $this->planId = $planId;

        return $this;
    }

    /**
     * set search query params
     *
     * @param array<mixed> $queryParams
     * @return SubscriptionInterface
     */
    public function setQueryParams(array $queryParams): SubscriptionInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * create subscription
     *
     * @param array<mixed> $subscription
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see https://www.mercadopago.com.br/developers/en/reference/subscriptions/_preapproval/post
     */
    public function create(array $subscription): array
    {
        if ($this->payerEmail !== null) {
            $subscription['payer_email'] = $subscription['payer_email'] ?? $this->payerEmail;
        }

        if ($this->planId !== null) {
            $subscription['preapproval_plan_id'] = $subscription['preapproval_plan_id'] ?? $this->planId;
        }

        MercadoPagoSubscriptionRequest::validate($subscription);

        return $this->post('preapproval', $subscription);
    }

    /**
     * find subscription by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("preapproval/{$id}");
    }

    /**
     * search subscriptions
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('preapproval/search', $this->queryParams);
    }

    /**
     * update subscription by id
     *
     * @param string $id
     * @param array<mixed> $subscription
     * @return array<mixed>
     * @throws ApiException
     */
    public function update(string $id, array $subscription): array
    {
        return $this->put("preapproval/{$id}", $subscription);
    }

    /**
     * pause subscription by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function pause(string $id): array
    {
        return $this->update($id, ['status' => SubscriptionStatusEnum::PAUSED->value]);
    }

    /**
     * cancel subscription by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $id): array
    {
        return $this->update($id, ['status' => SubscriptionStatusEnum::CANCELLED->value]);
    }
}
