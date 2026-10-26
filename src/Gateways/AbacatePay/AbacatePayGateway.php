<?php

namespace PHPay\AbacatePay;

use GuzzleHttp\Client;
use PHPay\AbacatePay\Interface\AbacatePayGatewayInterface;
use PHPay\AbacatePay\Requests\{AbacatePayBillingRequest, AbacatePayCustomerRequest};
use PHPay\AbacatePay\Resources\Charge\Charge;
use PHPay\AbacatePay\Resources\Coupon\Coupon;
use PHPay\AbacatePay\Resources\Customer\Customer;
use PHPay\AbacatePay\Resources\WebhookSignature\WebhookSignature;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\MasksCredentials;
use PHPay\Support\{ChargeIntent, Customer as CustomerData, Method};

class AbacatePayGateway implements AbacatePayGatewayInterface
{
    /**
     * keeps the credentials out of dumps
     */
    use MasksCredentials;

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
        #[\SensitiveParameter]
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
    public function customer(CustomerData|array $customer = []): Customer
    {
        if ($customer instanceof CustomerData) {
            $customer = AbacatePayCustomerRequest::fromCustomer($customer);
        }

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
     * the payment methods createCharge() accepts here.
     *
     * the AbacatePay only does Pix: `frequency` accepts ONE_TIME alone, and
     * `methods` accepts PIX alone.
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
     * the AbacatePay bills by products, never by an amount, and refuses a
     * product under one real — so a cheap scalar intent is refused here
     * rather than rounded up, which would charge the wrong value.
     *
     * the externalId is the product's id in YOUR system. When the item
     * carries no reference, it is derived from the name instead of being
     * random, so the same product does not get a new id on every charge.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createCharge(ChargeIntent $intent): array
    {
        $intent->ensureSupportedBy('AbacatePay', $this->supportedMethods());

        $charge = $this->charge()->setBilling(AbacatePayBillingRequest::fromIntent($intent));

        foreach ($intent->itemsOrOne() as $item) {
            $charge->addProduct(
                $item->reference ?? substr(sha1($item->name), 0, 16),
                $item->name,
                $item->unitAmount,
                $item->quantity
            );
        }

        if ($intent->customer !== null) {
            $charge->setCustomer($intent->customer);
        }

        return $charge->create();
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

    /**
     * webhook signature
     *
     * @return WebhookSignature
     */
    public function webhookSignature(): WebhookSignature
    {
        return new WebhookSignature();
    }
}
