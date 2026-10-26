<?php

namespace PHPay\PagBank;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\MasksCredentials;
use PHPay\PagBank\Interface\PagBankGatewayInterface;
use PHPay\PagBank\Requests\{PagBankCustomerRequest, PagBankOrderRequest, PagBankWebhookEventRequest};
use PHPay\PagBank\Resources\Charge\Charge;
use PHPay\PagBank\Resources\Customer\Customer;
use PHPay\PagBank\Resources\Subscription\Subscription;
use PHPay\PagBank\Resources\WebhookSignature\WebhookSignature;
use PHPay\Support\{ChargeIntent, Customer as CustomerData, Method, Money};
use PHPay\Webhooks\Event;

class PagBankGateway implements PagBankGatewayInterface
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
        return 'PagBank';
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
            $customer = PagBankCustomerRequest::fromCustomer($customer);
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
     * @param Money|int|null $amount null refunds the full value
     * @return array<mixed>
     * @throws ApiException
     */
    /**
     * the payment methods createCharge() accepts here.
     *
     * the PagBank also takes card through `charges`; it is absent because
     * the portable path cannot build it yet.
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
     * the PagBank has no amount on the order: it is the sum of `items`, and
     * the Pix amount is repeated in `qr_codes[0]`. A scalar intent becomes a
     * single line, which is what itemsOrOne() is for.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createCharge(ChargeIntent $intent): array
    {
        $intent->ensureSupportedBy('PagBank', $this->supportedMethods());

        $charge = $this->charge()->setOrder(PagBankOrderRequest::fromIntent($intent));

        foreach ($intent->itemsOrOne() as $item) {
            $charge->addItem($item->name, $item->unitAmount, $item->quantity);
        }

        if ($intent->customer !== null) {
            $charge->setCustomer($intent->customer);
        }

        return $charge->setQrCode($intent->amount)->create();
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
        return PagBankWebhookEventRequest::parse($payload);
    }
}
