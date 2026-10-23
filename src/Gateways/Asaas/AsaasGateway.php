<?php

namespace PHPay\Asaas;

use GuzzleHttp\Client;
use PHPay\Asaas\Interface\AsaasGatewayInterface;
use PHPay\Asaas\Requests\{AsaasCustomerRequest, AsaasWebhookEventRequest};
use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Pix\Pix;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Asaas\Resources\Webhook\Webhook;
use PHPay\Asaas\Resources\WebhookSignature\WebhookSignature;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\MasksCredentials;
use PHPay\Support\{Customer as CustomerData, Money};
use PHPay\Webhooks\Event;

class AsaasGateway implements AsaasGatewayInterface
{
    /**
     * keeps the credentials out of dumps
     */
    use MasksCredentials;

    /**
     * construct
     *
     * @param string $token
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $token,
        private bool $sandbox = true,
        private ?Client $client = null
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Asaas';
    }

    /**
     * customer
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(CustomerData|array $customer = []): Customer
    {
        if ($customer instanceof CustomerData) {
            $customer = AsaasCustomerRequest::fromCustomer($customer);
        }

        return new Customer($this->token, $customer, $this->sandbox, $this->client);
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->token, $this->sandbox, $this->client);
    }

    /**
     * refund a received payment, fully or in part.
     *
     * the capability's operation, delegated to the charge resource that owns
     * the endpoint.
     *
     * @param string $id
     * @param Money|int|float|null $amount null refunds the full value; reais
     *                                     here, as everywhere in this gateway
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $id, Money|int|float|null $amount = null): array
    {
        return $this->charge()->refund($id, $amount);
    }

    /**
     * webhook
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook
    {
        return new Webhook($this->token, $webhook, $this->sandbox, $this->client);
    }

    /**
     * pix
     *
     * @return Pix
     */
    public function pix(): Pix
    {
        return new Pix($this->token, $this->sandbox, $this->client);
    }

    /**
     * subscription
     *
     * @return Subscription
     */
    public function subscription(): Subscription
    {
        return new Subscription($this->token, $this->sandbox, $this->client);
    }

    /**
     * webhook signature
     *
     * @return WebhookSignature
     */
    public function webhookSignature(): WebhookSignature
    {
        return new WebhookSignature();
    }

    /**
     * read a webhook notification into events.
     *
     * returns a list, never a single event — see SupportsWebhookEvents.
     * Verify the signature first, with the raw body.
     *
     * @param string|array<mixed> $payload the raw body, or it already decoded
     * @return array<int, Event>
     * @throws ValidationException
     */
    public function webhookEvents(string|array $payload): array
    {
        return AsaasWebhookEventRequest::parse($payload);
    }
}
