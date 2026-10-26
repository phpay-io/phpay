<?php

namespace PHPay\PagarMe;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\MasksCredentials;
use PHPay\PagarMe\Interface\PagarMeGatewayInterface;
use PHPay\PagarMe\Requests\{PagarMeCustomerRequest, PagarMeOrderRequest, PagarMeWebhookEventRequest};
use PHPay\PagarMe\Resources\Charge\Charge;
use PHPay\PagarMe\Resources\Customer\Customer;
use PHPay\PagarMe\Resources\Subscription\Subscription;
use PHPay\PagarMe\Resources\WebhookDelivery\WebhookDelivery;
use PHPay\Support\{ChargeIntent, Customer as CustomerData, Method, Money};
use PHPay\Webhooks\Event;

class PagarMeGateway implements PagarMeGatewayInterface
{
    /**
     * keeps the credentials out of dumps
     */
    use MasksCredentials;

    /**
     * prefix that marks a test secret key
     */
    public const TEST_KEY_PREFIX = 'sk_test_';

    /**
     * construct
     *
     * there is no $sandbox flag: Pagar.me serves test and production from the
     * same host, and the secret key decides which one answers.
     *
     * @param string $secretKey
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        #[\SensitiveParameter]
        private string $secretKey,
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
        return 'Pagar.me';
    }

    /**
     * whether the credential in use is a test credential.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return str_starts_with($this->secretKey, self::TEST_KEY_PREFIX);
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
            $customer = PagarMeCustomerRequest::fromCustomer($customer);
        }

        return new Customer($this->secretKey, $customer, $this->client);
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->secretKey, $this->client);
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
    /**
     * the payment methods createCharge() accepts here.
     *
     * the Pagar.me is the gateway with the widest menu — card, debit, billet
     * and Pix — and only Pix is reachable from the portable path so far.
     *
     * @return array<int, Method>
     */
    public function supportedMethods(): array
    {
        return [Method::PIX];
    }

    /**
     * create a charge from a portable description.
     *
     * the Pagar.me has no amount on the order either: it is the sum of
     * `items`, and the payment method rides in `payments[]`.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createCharge(ChargeIntent $intent): array
    {
        $intent->ensureSupportedBy('Pagar.me', $this->supportedMethods());

        $charge = $this->charge()->setOrder(PagarMeOrderRequest::fromIntent($intent));

        foreach ($intent->itemsOrOne() as $item) {
            $charge->addItem($item->name, $item->unitAmount, $item->quantity);
        }

        if ($intent->customer !== null) {
            $charge->setCustomer($intent->customer);
        }

        return $charge->setPix()->create();
    }

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
        return new Subscription($this->secretKey, $this->client);
    }

    /**
     * read the webhook events already delivered by the gateway.
     *
     * @return WebhookDelivery
     */
    public function webhookDeliveries(): WebhookDelivery
    {
        return new WebhookDelivery($this->secretKey, $this->client);
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
        return PagarMeWebhookEventRequest::parse($payload);
    }
}
