<?php

namespace PHPay\AbacatePay\Resources\Coupon;

use GuzzleHttp\Client;
use PHPay\AbacatePay\Resources\Coupon\Interface\CouponInterface;
use PHPay\AbacatePay\Traits\HasAbacatePayClient;
use PHPay\Exceptions\ApiException;

/**
 * discount coupons of the AbacatePay API.
 *
 * no other gateway in the library has this, so it is not a capability — it is
 * reachable only from the concrete AbacatePayGateway, the same place the
 * Pagar.me webhook deliveries live.
 */
class Coupon implements CouponInterface
{
    /**
     * trait abacatepay client
     */
    use HasAbacatePayClient;

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
     * @param string $token
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientAbacatePayBoot();
    }

    /**
     * create coupon
     *
     * @param array<mixed> $coupon
     * @return array<mixed>
     * @throws ApiException
     */
    public function create(array $coupon): array
    {
        return $this->post('coupon/create', $coupon);
    }

    /**
     * list coupons
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('coupon/list', $this->queryParams);
    }

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return CouponInterface
     */
    public function setQueryParams(array $queryParams): CouponInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }
}
