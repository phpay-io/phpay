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
     * fine, interest and write-off of the billet.
     *
     * **null is not the same as an empty array.** null means nobody chose, so
     * the historical default applies; an empty array is refused, because it
     * does not say "no charges" and Efí does not document whether the object
     * can be omitted at all.
     *
     * @var array<mixed>|null
     */
    private ?array $configuration = null;

    /**
     * construct
     *
     * @param array<string, mixed> $token
     * @param array<mixed> $charge
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        #[\SensitiveParameter]
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
     * set the items of the charge.
     *
     * the gateway's payload has no charge-level amount — it has `items`, and
     * setAmount() plus the charge `description` are a shortcut that builds one.
     * This is the way to bill more than one line.
     *
     * each item needs `name` and `value` **in cents**; any other field Efí
     * accepts (`amount`, for instance) passes through untouched.
     *
     * ```php
     * $charge->setItems([
     *     ['name' => 'Camiseta', 'value' => 4990, 'amount' => 2],
     *     ['name' => 'Frete',    'value' => 1500],
     * ]);
     * ```
     *
     * @param array<mixed> $items
     * @return ChargeInterface
     * @throws ValidationException
     */
    public function setItems(array $items): ChargeInterface
    {
        EfiChargeRequest::validateItems($items);

        $this->items = $items;

        return $this;
    }

    /**
     * set fine, interest and automatic write-off of the billet.
     *
     * **the two scales are different, and that is Efí's own doing:** `fine` is
     * percent × 100, so `200` is 2%; `interest` is percent × 1000, so `33` is
     * 0.033%. Both are integers for that reason — 2.5% of fine is `250`, not
     * `2.5`.
     *
     * `interest` also takes the object form `['value' => 330, 'type' =>
     * 'monthly']`; omitting the type makes Efí calculate per day. **The scale
     * of the monthly form is not documented by Efí**, so nothing here converts
     * it — the value goes as you wrote it.
     *
     * ```php
     * $charge->setConfigurations([
     *     'fine'              => 200,   // 2%
     *     'interest'          => 33,    // 0,033% ao dia
     *     'days_to_write_off' => 40,
     * ]);
     *
     * $charge->setConfigurations(['fine' => 0, 'interest' => 0]);   // sem encargo
     * ```
     *
     * @param array<mixed> $configurations
     * @return ChargeInterface
     * @throws ValidationException
     */
    public function setConfigurations(array $configurations): ChargeInterface
    {
        EfiChargeRequest::validateConfigurations($configurations);

        $this->configuration = $configurations;

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
        EfiChargeRequest::validate($this->charge, $this->customer, $this->items !== []);

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
     * the items of the request: the ones that were set, or one built from the
     * charge's description and amount.
     *
     * **this reads the state and does not touch it.** It used to `unset()` the
     * description and the value after building the item, which made a second
     * `create()` on the same Resource fail validation — and the message blamed
     * the caller for a field they had set. The unsets served nothing: the
     * payload is assembled from the items and from `expire_at`, and never
     * reads those two back.
     *
     * @return array<mixed>
     */
    private function getItems(): array
    {
        if ($this->items !== []) {
            return $this->items;
        }

        $value = $this->charge['value'];

        return [[
            'name'  => $this->charge['description'],
            'value' => $value instanceof Money ? $value->toCentavos() : $value,
        ]];
    }

    /**
     * the `configurations` of the request.
     *
     * the default is 2% of fine (`200`) and 0.033% of interest per day (`33`),
     * which is what this gateway has always sent. **It stays because removing
     * it would be a behaviour change in a minor**: a billet from an
     * integration that never configured anything would stop carrying charges
     * the merchant may be counting on. What changed is that it is now visible
     * and overridable — `setConfigurations(['fine' => 0, 'interest' => 0])`
     * bills without them.
     *
     * @return array<mixed>
     */
    private function getConfigurations(): array
    {
        return $this->configuration ?? [
            'fine'     => 200,
            'interest' => 33,
        ];
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
