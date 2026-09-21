<?php

namespace PHPay\Cielo;

use GuzzleHttp\Client;
use PHPay\Cielo\Interface\CieloGatewayInterface;
use PHPay\Cielo\Resources\Charge\Charge;
use PHPay\Cielo\Resources\Subscription\Subscription;

class CieloGateway implements CieloGatewayInterface
{
    /**
     * construct
     *
     * @param string $merchantId
     * @param string $merchantKey
     * @param bool $sandbox
     * @param Client|null $client injected write client, mainly for tests
     * @param Client|null $queryClient injected query client, mainly for tests
     */
    public function __construct(
        private string $merchantId,
        private string $merchantKey,
        private bool $sandbox = true,
        private ?Client $client = null,
        private ?Client $queryClient = null,
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Cielo';
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge(
            $this->merchantId,
            $this->merchantKey,
            $this->sandbox,
            $this->client,
            $this->queryClient
        );
    }

    /**
     * subscription
     *
     * @return Subscription
     */
    public function subscription(): Subscription
    {
        return new Subscription(
            $this->merchantId,
            $this->merchantKey,
            $this->sandbox,
            $this->client,
            $this->queryClient
        );
    }
}
