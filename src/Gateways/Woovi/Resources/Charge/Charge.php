<?php

namespace PHPay\Woovi\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{Customer as CustomerData, Money};
use PHPay\Woovi\Requests\{WooviChargeRequest, WooviCustomerRequest};
use PHPay\Woovi\Resources\Charge\Interface\ChargeInterface;
use PHPay\Woovi\Traits\HasWooviClient;

/**
 * charges of the Woovi/OpenPix API.
 *
 * every object is addressable by your own correlationID instead of the
 * gateway id, which is why find() and destroy() take either. Values are
 * integers in cents.
 */
class Charge implements ChargeInterface
{
    /**
     * trait woovi client
     */
    use HasWooviClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $charge = [];

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * construct
     *
     * @param string $appId
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $appId,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientWooviBoot();
    }

    /**
     * set the whole charge payload
     *
     * @param array<mixed> $charge
     * @return ChargeInterface
     */
    public function setCharge(array $charge): ChargeInterface
    {
        $this->charge = $charge;

        return $this;
    }

    /**
     * set the identifier of this charge in your own system.
     *
     * it is not optional on Woovi: the API requires it, and it is what you
     * use to look the charge up later.
     *
     * @param string $correlationId
     * @return ChargeInterface
     */
    public function setCorrelationId(string $correlationId): ChargeInterface
    {
        $this->charge['correlationID'] = $correlationId;

        return $this;
    }

    /**
     * set the customer of the charge
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(CustomerData|array $customer): ChargeInterface
    {
        if ($customer instanceof CustomerData) {
            $customer = WooviCustomerRequest::fromCustomer($customer);
        }

        $this->charge['customer'] = $customer;

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
     * create the charge
     *
     * @param int $value amount in cents
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(Money|int $value): array
    {
        $this->charge['value']         = Money::asCentavos($value);
        $this->charge['correlationID'] = $this->charge['correlationID'] ?? uniqid('phpay_');

        WooviChargeRequest::validate($this->charge);

        return $this->post('api/v1/charge', $this->charge);
    }

    /**
     * find a charge by correlationID or by the gateway id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("api/v1/charge/{$id}");
    }

    /**
     * list charges
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('api/v1/charge', $this->queryParams);
    }

    /**
     * delete a charge
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function destroy(string $id): array
    {
        return $this->request('DELETE', "api/v1/charge/{$id}");
    }

    /**
     * get the Pix copy-and-paste code of a created charge
     *
     * @param array<mixed> $charge the response of create()
     * @return string|null
     */
    public function getPixCode(array $charge): ?string
    {
        $data = $charge['charge'] ?? $charge;

        if (!is_array($data)) {
            return null;
        }

        $code = $data['brCode'] ?? null;

        return is_string($code) ? $code : null;
    }
}
