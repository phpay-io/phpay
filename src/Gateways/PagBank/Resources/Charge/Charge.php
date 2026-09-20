<?php

namespace PHPay\PagBank\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\PagBank\Requests\PagBankOrderRequest;
use PHPay\PagBank\Resources\Charge\Interface\ChargeInterface;
use PHPay\PagBank\Traits\HasPagBankClient;

/**
 * orders and charges of the PagBank Orders API.
 *
 * every monetary field is an integer in cents: R$ 10,50 is 1050. PagBank
 * rejects decimals, and sending 10.50 would charge eleven cents.
 */
class Charge implements ChargeInterface
{
    /**
     * trait pagbank client
     */
    use HasPagBankClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $order = [];

    /**
     * construct
     *
     * @param string $token
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientPagBankBoot();
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
     * set the customer of the order.
     *
     * on the Orders API the customer travels inside the order — it is not a
     * resource of its own. The /customers CRUD belongs to subscriptions.
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(array $customer): ChargeInterface
    {
        $this->order['customer'] = $customer;

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
     * @param string $name
     * @param int $unitAmount amount in cents
     * @param int $quantity
     * @return ChargeInterface
     */
    public function addItem(string $name, int $unitAmount, int $quantity = 1): ChargeInterface
    {
        $items = $this->order['items'] ?? [];

        if (!is_array($items)) {
            $items = [];
        }

        $items[] = [
            'reference_id' => uniqid('item_'),
            'name'         => $name,
            'quantity'     => $quantity,
            'unit_amount'  => $unitAmount,
        ];

        $this->order['items'] = $items;

        return $this;
    }

    /**
     * set the charges of the order (card or boleto)
     *
     * @param array<mixed> $charges
     * @return ChargeInterface
     */
    public function setCharges(array $charges): ChargeInterface
    {
        $this->order['charges'] = $charges;

        return $this;
    }

    /**
     * request a Pix QR Code for the order.
     *
     * Pix does not travel as a charge on PagBank: the order carries a qr_codes
     * entry, and the copy-and-paste code comes back in qr_codes[0].text. Only
     * one QR Code per order is supported, and the account needs an active Pix
     * key.
     *
     * @param int $amount amount in cents
     * @param string|null $expiresAt defaults to 23:59:59 of the next day
     * @return ChargeInterface
     */
    public function setQrCode(int $amount, ?string $expiresAt = null): ChargeInterface
    {
        $qrCode = ['amount' => ['value' => $amount]];

        if ($expiresAt !== null) {
            $qrCode['expiration_date'] = $expiresAt;
        }

        $this->order['qr_codes'] = [$qrCode];

        return $this;
    }

    /**
     * set the urls notified about order events.
     *
     * PagBank has no webhook CRUD — this is the per-order way to be notified.
     *
     * @param array<int, string> $urls
     * @return ChargeInterface
     */
    public function setNotificationUrls(array $urls): ChargeInterface
    {
        $this->order['notification_urls'] = array_values($urls);

        return $this;
    }

    /**
     * create the order
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see https://developer.pagbank.com.br/reference/criar-pedido-simples
     */
    public function create(): array
    {
        $this->order['reference_id'] = $this->order['reference_id'] ?? uniqid('phpay_');

        PagBankOrderRequest::validate($this->order);

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
     * get the Pix copy-and-paste code of an order
     *
     * @param string $id
     * @return string|null
     * @throws ApiException
     */
    public function getPixCode(string $id): ?string
    {
        $order = $this->find($id);

        $qrCodes = $order['qr_codes'] ?? null;

        if (!is_array($qrCodes) || empty($qrCodes)) {
            return null;
        }

        $first = reset($qrCodes);

        if (!is_array($first)) {
            return null;
        }

        $text = $first['text'] ?? null;

        return is_string($text) ? $text : null;
    }

    /**
     * refund a charge, fully or partially.
     *
     * undoes a pre-authorization or gives back a captured payment. PagBank
     * accepts refunds for up to 350 days after authorization.
     *
     * @param string $id
     * @param int|null $amount amount in cents; null refunds the full value
     * @return array<mixed>
     * @throws ApiException
     * @see https://developer.pagbank.com.br/reference/cancelar-pagamento
     */
    public function refund(string $id, ?int $amount = null): array
    {
        return $this->post(
            "charges/{$id}/cancel",
            $amount === null ? [] : ['amount' => ['value' => $amount]]
        );
    }
}
