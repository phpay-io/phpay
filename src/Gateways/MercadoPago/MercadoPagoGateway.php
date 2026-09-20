<?php

namespace PHPay\MercadoPago;

use GuzzleHttp\Client;
use PHPay\MercadoPago\Interface\MercadoPagoGatewayInterface;
use PHPay\MercadoPago\Resources\Charge\Charge;
use PHPay\MercadoPago\Resources\Customer\Customer;
use PHPay\MercadoPago\Resources\Subscription\Subscription;

class MercadoPagoGateway implements MercadoPagoGatewayInterface
{
    /**
     * prefix that marks a test credential
     */
    public const TEST_TOKEN_PREFIX = 'TEST-';

    /**
     * construct
     *
     * there is no $sandbox flag: Mercado Pago has a single host and the
     * environment is decided by the access token, which is prefixed with
     * "TEST-" for test credentials.
     *
     * @param string $accessToken
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $accessToken,
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
        return 'Mercado Pago';
    }

    /**
     * whether the credential in use is a test credential.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return str_starts_with($this->accessToken, self::TEST_TOKEN_PREFIX);
    }

    /**
     * customer
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(array $customer = []): Customer
    {
        return new Customer($this->accessToken, $customer, $this->client);
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->accessToken, $this->client);
    }

    /**
     * subscription
     *
     * @return Subscription
     */
    public function subscription(): Subscription
    {
        return new Subscription($this->accessToken, $this->client);
    }
}
