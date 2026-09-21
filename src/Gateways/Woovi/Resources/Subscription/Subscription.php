<?php

namespace PHPay\Woovi\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{Customer as CustomerData, Money};
use PHPay\Woovi\Requests\{WooviCustomerRequest, WooviSubscriptionRequest};
use PHPay\Woovi\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\Woovi\Traits\HasWooviClient;

/**
 * subscriptions of the Woovi/OpenPix API — recurring charges paid with Pix,
 * without the customer re-entering anything each cycle.
 */
class Subscription implements SubscriptionInterface
{
    /**
     * trait woovi client
     */
    use HasWooviClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $subscription = [];

    /**
     * construct
     *
     * @param string $appId
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $appId,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientWooviBoot();
    }

    /**
     * set the customer of the subscription
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(CustomerData|array $customer): SubscriptionInterface
    {
        if ($customer instanceof CustomerData) {
            $customer = WooviCustomerRequest::fromCustomer($customer);
        }

        $this->subscription['customer'] = $customer;

        return $this;
    }

    /**
     * set the day of the month the charge is generated
     *
     * @param int $day
     * @return SubscriptionInterface
     */
    public function setDayGenerateCharge(int $day): SubscriptionInterface
    {
        $this->subscription['dayGenerateCharge'] = $day;

        return $this;
    }

    /**
     * create the subscription
     *
     * @param int $value amount in cents
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(Money|int $value): array
    {
        $this->subscription['value'] = Money::asCentavos($value);

        WooviSubscriptionRequest::validate($this->subscription);

        return $this->post('api/v1/subscriptions', $this->subscription);
    }

    /**
     * find a subscription by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("api/v1/subscriptions/{$id}");
    }
}
