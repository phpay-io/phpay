<?php

namespace PHPay\AbacatePay\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\AbacatePay\Enums\{BillingFrequencyEnum, BillingMethodEnum};
use PHPay\AbacatePay\Requests\{AbacatePayBillingRequest, AbacatePayCustomerRequest};
use PHPay\AbacatePay\Resources\Charge\Interface\ChargeInterface;
use PHPay\AbacatePay\Traits\HasAbacatePayClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{Customer as CustomerData, Money};

/**
 * billings of the AbacatePay API.
 *
 * a billing is a payment link, and it is built out of products rather than a
 * single amount: the total comes back calculated in `amount`. Prices are
 * integers in cents, with a floor of 100 (R$ 1,00) per product.
 */
class Charge implements ChargeInterface
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
    private array $billing = [];

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
     * set the whole billing payload
     *
     * @param array<mixed> $billing
     * @return ChargeInterface
     */
    public function setBilling(array $billing): ChargeInterface
    {
        $this->billing = $billing;

        return $this;
    }

    /**
     * attach an existing customer to the billing
     *
     * @param string $customerId
     * @return ChargeInterface
     */
    public function setCustomerId(string $customerId): ChargeInterface
    {
        $this->billing['customerId'] = $customerId;

        unset($this->billing['customer']);

        return $this;
    }

    /**
     * attach a customer created along with the billing
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(CustomerData|array $customer): ChargeInterface
    {
        if ($customer instanceof CustomerData) {
            if ($customer->id !== null) {
                return $this->setCustomerId($customer->id);
            }

            $customer = AbacatePayCustomerRequest::fromCustomer($customer);
        }

        if (isset($customer['id']) && is_string($customer['id']) && $customer['id'] !== '') {
            return $this->setCustomerId($customer['id']);
        }

        $this->billing['customer'] = $customer;

        unset($this->billing['customerId']);

        return $this;
    }

    /**
     * set the products being charged
     *
     * @param array<mixed> $products
     * @return ChargeInterface
     */
    public function setProducts(array $products): ChargeInterface
    {
        $this->billing['products'] = $products;

        return $this;
    }

    /**
     * append a single product to the billing.
     *
     * the externalId is the product id in YOUR system: AbacatePay creates the
     * product on its side from it, so it has to be unique.
     *
     * @param string $externalId
     * @param string $name
     * @param int $price price per unit in cents, minimum 100
     * @param int $quantity
     * @param string|null $description
     * @return ChargeInterface
     */
    public function addProduct(
        string $externalId,
        string $name,
        Money|int $price,
        int $quantity = 1,
        ?string $description = null
    ): ChargeInterface {
        $products = $this->billing['products'] ?? [];

        if (!is_array($products)) {
            $products = [];
        }

        $product = [
            'externalId' => $externalId,
            'name'       => $name,
            'quantity'   => $quantity,
            'price'      => Money::asCentavos($price),
        ];

        if ($description !== null) {
            $product['description'] = $description;
        }

        $products[] = $product;

        $this->billing['products'] = $products;

        return $this;
    }

    /**
     * set where the customer goes after paying, and if they give up.
     *
     * both are required by the API — a billing is a hosted payment link.
     *
     * @param string $completionUrl
     * @param string $returnUrl
     * @return ChargeInterface
     */
    public function setUrls(string $completionUrl, string $returnUrl): ChargeInterface
    {
        $this->billing['completionUrl'] = $completionUrl;
        $this->billing['returnUrl']     = $returnUrl;

        return $this;
    }

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return ChargeInterface
     */
    public function setQueryParams(array $queryParams): ChargeInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * create the billing
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        $this->billing['frequency'] = $this->billing['frequency'] ?? BillingFrequencyEnum::ONE_TIME->value;
        $this->billing['methods']   = $this->billing['methods'] ?? [BillingMethodEnum::PIX->value];

        AbacatePayBillingRequest::validate($this->billing);

        return $this->post('billing/create', $this->billing);
    }

    /**
     * list billings
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('billing/list', $this->queryParams);
    }

    /**
     * get the payment link of a created billing
     *
     * @param array<mixed> $billing the response of create()
     * @return string|null
     */
    public function getPaymentUrl(array $billing): ?string
    {
        $data = $this->payload($billing);

        $url = $data['url'] ?? null;

        return is_string($url) ? $url : null;
    }

    /**
     * whether a created billing was made with a dev mode key.
     *
     * the gateway has a single host and the key carries no prefix, so this is
     * the only honest way to know which environment answered.
     *
     * @param array<mixed> $billing the response of create()
     * @return bool|null
     */
    public function isDevMode(array $billing): ?bool
    {
        $data = $this->payload($billing);

        $devMode = $data['devMode'] ?? null;

        return is_bool($devMode) ? $devMode : null;
    }

    /**
     * unwrap the `data` envelope the API answers with, when it is there.
     *
     * @param array<mixed> $response
     * @return array<mixed>
     */
    private function payload(array $response): array
    {
        $data = $response['data'] ?? null;

        return is_array($data) ? $data : $response;
    }
}
