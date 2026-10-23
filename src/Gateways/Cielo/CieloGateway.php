<?php

namespace PHPay\Cielo;

use GuzzleHttp\Client;
use PHPay\Cielo\Interface\CieloGatewayInterface;
use PHPay\Cielo\Resources\Charge\Charge;
use PHPay\Cielo\Resources\Subscription\Subscription;
use PHPay\Exceptions\ApiException;
use PHPay\Http\MasksCredentials;
use PHPay\Support\Money;

class CieloGateway implements CieloGatewayInterface
{
    /**
     * keeps the credentials out of dumps
     */
    use MasksCredentials;

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
        #[\SensitiveParameter]
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
     * refund a received payment, fully or in part.
     *
     * the capability's operation, delegated to the charge resource that owns
     * the endpoint.
     *
     * @param string $id
     * @param Money|int|null $amount null refunds the full value
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $id, Money|int|null $amount = null): array
    {
        return $this->charge()->refund($id, $amount);
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
