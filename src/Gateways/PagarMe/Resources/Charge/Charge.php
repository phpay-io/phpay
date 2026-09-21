<?php

namespace PHPay\PagarMe\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\PagarMe\Enums\PaymentMethodEnum;
use PHPay\PagarMe\Requests\PagarMeOrderRequest;
use PHPay\PagarMe\Resources\Charge\Interface\ChargeInterface;
use PHPay\PagarMe\Traits\HasPagarMeClient;
use PHPay\Support\Money;

/**
 * orders and charges of the Pagar.me Core API v5.
 *
 * every monetary field is an integer in cents: R$ 10,50 is 1050.
 */
class Charge implements ChargeInterface
{
    /**
     * trait pagar.me client
     */
    use HasPagarMeClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $order = [];

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * construct
     *
     * @param string $secretKey
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $secretKey,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientPagarMeBoot();
    }

    /**
     * set the whole order payload
     *
     * @param array<mixed> $order
     * @return ChargeInterface
     */
    public function setOrder(array $order): ChargeInterface
    {
        $this->order = $order;

        return $this;
    }

    /**
     * attach an existing customer to the order
     *
     * @param string $customerId
     * @return ChargeInterface
     */
    public function setCustomerId(string $customerId): ChargeInterface
    {
        $this->order['customer_id'] = $customerId;

        unset($this->order['customer']);

        return $this;
    }

    /**
     * attach a customer created along with the order.
     *
     * Pagar.me accepts the customer inline, so no extra call is needed — pass
     * an array carrying `id` to reuse an existing one instead.
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(array $customer): ChargeInterface
    {
        if (isset($customer['id']) && is_string($customer['id']) && $customer['id'] !== '') {
            return $this->setCustomerId($customer['id']);
        }

        $this->order['customer'] = $customer;

        unset($this->order['customer_id']);

        return $this;
    }

    /**
     * set the items of the order
     *
     * @param array<mixed> $items
     * @return ChargeInterface
     */
    public function setItems(array $items): ChargeInterface
    {
        $this->order['items'] = $items;

        return $this;
    }

    /**
     * append a single item to the order
     *
     * @param string $description
     * @param int $amount amount in cents
     * @param int $quantity
     * @return ChargeInterface
     */
    public function addItem(string $description, Money|int $amount, int $quantity = 1): ChargeInterface
    {
        $items = $this->order['items'] ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        $items[] = [
            'code'        => uniqid('item_'),
            'description' => $description,
            'amount'      => Money::asCentavos($amount),
            'quantity'    => $quantity,
        ];

        $this->order['items'] = $items;

        return $this;
    }

    /**
     * set the payments of the order
     *
     * @param array<mixed> $payments
     * @return ChargeInterface
     */
    public function setPayments(array $payments): ChargeInterface
    {
        $this->order['payments'] = $payments;

        return $this;
    }

    /**
     * pay the order with Pix.
     *
     * on Pagar.me Pix is a payment method of the order, not a resource of its
     * own — the copy-and-paste code comes back inside the charge's last
     * transaction.
     *
     * @param int $expiresIn seconds until the QR Code expires
     * @return ChargeInterface
     */
    public function setPix(int $expiresIn = 3600): ChargeInterface
    {
        return $this->setPayments([[
            'payment_method' => PaymentMethodEnum::PIX->value,
            'pix'            => ['expires_in' => $expiresIn],
        ]]);
    }

    /**
     * pay the order with boleto
     *
     * @param string|null $dueAt
     * @param array<mixed> $instructions
     * @return ChargeInterface
     */
    public function setBoleto(?string $dueAt = null, array $instructions = []): ChargeInterface
    {
        $boleto = [];

        if ($dueAt !== null) {
            $boleto['due_at'] = $dueAt;
        }

        if (!empty($instructions)) {
            $boleto['instructions'] = $instructions;
        }

        return $this->setPayments([[
            'payment_method' => PaymentMethodEnum::BOLETO->value,
            'boleto'         => $boleto,
        ]]);
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
     * create the order
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see https://docs.pagar.me/reference/criar-pedido-2
     */
    public function create(): array
    {
        PagarMeOrderRequest::validate($this->order);

        return $this->post('orders', $this->order);
    }

    /**
     * find order by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("orders/{$id}");
    }

    /**
     * list orders
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('orders', $this->queryParams);
    }

    /**
     * find charge by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function findCharge(string $id): array
    {
        return $this->get("charges/{$id}");
    }

    /**
     * get the status of a charge
     *
     * @param string $id
     * @return string|null
     * @throws ApiException
     */
    public function getStatus(string $id): ?string
    {
        $charge = $this->findCharge($id);

        return isset($charge['status']) && is_string($charge['status'])
            ? $charge['status']
            : null;
    }

    /**
     * get the Pix copy-and-paste code of an order.
     *
     * it travels in charges[0].last_transaction.qr_code.
     *
     * @param string $id
     * @return string|null
     * @throws ApiException
     */
    public function getPixCode(string $id): ?string
    {
        $order = $this->find($id);

        $charges = $order['charges'] ?? null;

        if (!is_array($charges) || empty($charges)) {
            return null;
        }

        $charge = reset($charges);

        if (!is_array($charge)) {
            return null;
        }

        $transaction = $charge['last_transaction'] ?? null;

        if (!is_array($transaction)) {
            return null;
        }

        $code = $transaction['qr_code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * capture a previously authorized charge
     *
     * @param string $id
     * @param int|null $amount amount in cents
     * @return array<mixed>
     * @throws ApiException
     */
    public function capture(string $id, Money|int|null $amount = null): array
    {
        return $this->post(
            "charges/{$id}/capture",
            $amount === null ? [] : ['amount' => Money::asCentavos($amount)]
        );
    }

    /**
     * cancel a charge, refunding fully or partially.
     *
     * Pagar.me cancels through DELETE, with the amount in the body for a
     * partial refund.
     *
     * @param string $id
     * @param int|null $amount amount in cents; null refunds the full value
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $id, Money|int|null $amount = null): array
    {
        return $this->request(
            'DELETE',
            "charges/{$id}",
            ['json' => $amount === null ? [] : ['amount' => Money::asCentavos($amount)]]
        );
    }
}
