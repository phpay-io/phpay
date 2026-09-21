<?php

namespace PHPay\AbacatePay;

use GuzzleHttp\Client;
use PHPay\AbacatePay\Interface\AbacatePayGatewayInterface;
use PHPay\AbacatePay\Resources\Charge\Charge;
use PHPay\AbacatePay\Resources\Coupon\Coupon;
use PHPay\AbacatePay\Resources\Customer\Customer;

class AbacatePayGateway implements AbacatePayGatewayInterface
{
    /**
     * construct
     *
     * there is no $sandbox flag. AbacatePay serves one host, and which
     * environment answers depends on whether the key was created in dev mode
     * or in production — the key carries no prefix that tells them apart, so
     * the library does not guess. A created billing reports it in `devMode`.
     *
     * @param string $token
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        private ?Client $client = null,
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'AbacatePay';
    }

    /**
     * customer
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(array $customer = []): Customer
    {
        return new Customer($this->token, $customer, $this->client);
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->token, $this->client);
    }

    /**
     * discount coupons
     *
     * @return Coupon
     */
    public function coupons(): Coupon
    {
        return new Coupon($this->token, $this->client);
    }
}
