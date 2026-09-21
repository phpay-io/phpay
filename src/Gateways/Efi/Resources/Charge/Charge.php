<?php

namespace PHPay\Efi\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Efi\Requests\{EfiChargeRequest, EfiCustomerRequest};
use PHPay\Efi\Resources\Charge\Interface\ChargeInterface;
use PHPay\Efi\Traits\HasEfiClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{Customer, Money};

class Charge implements ChargeInterface
{
    /**
     * trait Efi client
     */
    use HasEfiClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $customer = [];

    /**
     * @var array<mixed>
     */
    private array $items = [];

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * @var array<mixed>
     */
    private array $configuration = [];

    /**
     * construct
     *
     * @param array<string, mixed> $token
     * @param array<mixed> $charge
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        array $token,
        private array $charge = [],
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $accessToken = $token['access_token'] ?? null;
        $tokenType   = $token['token_type'] ?? null;

        if (!is_string($accessToken) || !is_string($tokenType)) {
            throw ValidationException::make(
                'Efí',
                'Token inválido: access_token e token_type devem ser strings.'
            );
        }

        $this->client = $client ?? $this->clientEfiBoot($accessToken, $tokenType);
    }

    /**
     * set customer
     *
     * @param array<mixed> $customer
     * @return Charge
     */
    public function setCustomer(Customer|array $customer): Charge
    {
        if ($customer instanceof Customer) {
            $customer = EfiCustomerRequest::fromCustomer($customer);
        }

        $this->customer = $this->bootCustomer($customer);

        return $this;
    }

    /**
     * set the amount of the charge.
     *
     * Efí takes cents as an integer — pass a Money and the unit is handled
     * for you, or a raw integer, which is read as cents.
     *
     * @param Money|int $amount
     * @return ChargeInterface
     */
    public function setAmount(Money|int $amount): ChargeInterface
    {
        $this->charge['value'] = Money::asCentavos($amount);

        return $this;
    }

    /**
     * set filters
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
     * find all charges
     *
     * @return array<array|mixed>
     */
    public function getAll(): array
    {
        if (empty($this->queryParams)) {
            $this->setQueryParams([
                'charge_type' => 'billet',
                'begin_date'  => date('Y-m-d', strtotime('-30 days')),
                'end_date'    => date('Y-m-d'),
                'status'      => 'unpaid',
            ]);
        }

        return $this->get('v1/charges', $this->queryParams);
    }

    /**
     * find charge by id
     *
     * @return array<array|mixed>
     */
    public function find(string $id): array
    {
        return $this->get("v1/charge/{$id}");
    }

    /**
     * create charge
     *
     * @return array<mixed>
     * @see fields available in https://docs.Efi.com/reference/criar-nova-cobranca
     */
    public function create(): array
    {
        EfiChargeRequest::validate($this->charge, $this->customer);

        $items          = $this->getItems();
        $configurations = $this->getConfigurations();

        return $this->post('v1/charge/one-step', [
            'items'   => $items,
            'payment' => [
                'banking_billet' => [
                    'expire_at'      => $this->charge['expire_at'],
                    'customer'       => $this->customer,
                    'configurations' => $configurations,
                ],
            ],
        ]);
    }

    /**
     * update billet metadata
     * notification_url and custom_id
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function updateMetadata(string $id, array $data): array
    {
        return $this->put("v1/charge/{$id}/metadata", $data);
    }

    /**
     * cancel charge
     *
     * @param string $id
     * @return array<array|mixed>
     */
    public function cancel(string $id): array
    {
        return $this->put("v1/charge/{$id}/cancel", []);
    }

    /**
     * update due date
     *
     * @param string $id
     * @param string $dueDate
     * @return array<array|mixed>
     */
    public function updateDueDate(string $id, string $dueDate): array
    {
        return $this->put("v1/charge/{$id}/billet", [
            'expire_at' => $dueDate,
        ]);
    }

    /**
     * get status charge
     *
     * Efí has no dedicated status endpoint — the charge detail carries `status`.
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function getStatus(string $id): array
    {
        return $this->get("v1/charge/{$id}");
    }

    /**
     * confirm receipt
     *
     * @param string $id
     * @return array<mixed>
     */
    public function confirmReceipt(string $id): array
    {
        return $this->put("v1/charge/{$id}/settle", []);
    }

    /**
     * get items
     *
     * @return array<mixed>
     */
    private function getItems(): array
    {
        $items = $this->items;

        if (empty($this->items)) {
            $items[] = [
                'name'  => $this->charge['description'],
                'value' => $this->charge['value'],
            ];

            unset($this->charge['description']);
            unset($this->charge['value']);
        }

        return $items;
    }

    /**
     * get configuration
     *
     * @return array<mixed>
     */
    private function getConfigurations(): array
    {
        $configurations = $this->configuration;

        if (empty($configurations)) {
            $configurations = [
                'fine'     => 200,
                'interest' => 33,
            ];
        }

        return $configurations;
    }

    /**
     * boot customer
     *
     * @param array<mixed> $customer
     * @return array<mixed>
     * @throws ValidationException
     */
    private function bootCustomer(array $customer): array
    {
        EfiCustomerRequest::validate($customer);

        $document = $customer['cpf_cnpj'];

        if (!is_string($document)) {
            throw ValidationException::make('Efí', EfiCustomerRequest::messages()->cpfCnpj);
        }

        $customerMounted = strlen($document) === 11
            ? [
                'name' => $customer['name'],
                'cpf'  => $document,
            ]
            : [
                'juridical_person' => [
                    'corporate_name' => $customer['name'],
                    'cnpj'           => $document,
                ],
            ];

        foreach (['email', 'phone_number'] as $optional) {
            if (array_key_exists($optional, $customer)) {
                $customerMounted[$optional] = $customer[$optional];
            }
        }

        return $customerMounted;
    }
}
