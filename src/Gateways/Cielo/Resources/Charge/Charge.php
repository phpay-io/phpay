<?php

namespace PHPay\Cielo\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Cielo\Enums\PaymentTypeEnum;
use PHPay\Cielo\Requests\CieloSaleRequest;
use PHPay\Cielo\Resources\Charge\Interface\ChargeInterface;
use PHPay\Cielo\Traits\HasCieloClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\Money;

/**
 * sales of the Cielo E-commerce API 3.0.
 *
 * writes go to the api host and reads to the query host, so this resource
 * holds two clients. Every monetary field is an integer in cents: R$ 157,00
 * is 15700.
 */
class Charge implements ChargeInterface
{
    /**
     * trait cielo client
     */
    use HasCieloClient;

    /**
     * client used for writes
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $sale = [];

    /**
     * idempotency key sent as RequestId
     */
    private ?string $requestId = null;

    /**
     * construct
     *
     * @param string $merchantId
     * @param string $merchantKey
     * @param bool $sandbox
     * @param Client|null $client injected write client, mainly for tests
     * @param Client|null $queryClient injected query client, mainly for tests
     */
    public function __construct(
        private string $merchantId,
        private string $merchantKey,
        private bool $sandbox = true,
        ?Client $client = null,
        ?Client $queryClient = null,
    ) {
        $this->client      = $client ?? $this->clientCieloBoot();
        $this->queryClient = $queryClient ?? $client ?? $this->clientCieloQueryBoot();
    }

    /**
     * set the whole sale payload
     *
     * @param array<mixed> $sale
     * @return ChargeInterface
     */
    public function setSale(array $sale): ChargeInterface
    {
        $this->sale = $sale;

        return $this;
    }

    /**
     * set the order identifier of your own system
     *
     * @param string $merchantOrderId
     * @return ChargeInterface
     */
    public function setOrderId(string $merchantOrderId): ChargeInterface
    {
        $this->sale['MerchantOrderId'] = $merchantOrderId;

        return $this;
    }

    /**
     * set the customer of the sale.
     *
     * the Cielo E-commerce API has no customer resource — the customer lives
     * inside the sale, which is why this gateway does not declare
     * SupportsCustomers.
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     */
    public function setCustomer(array $customer): ChargeInterface
    {
        $this->sale['Customer'] = $customer;

        return $this;
    }

    /**
     * pay with Pix
     *
     * @param int $amount amount in cents
     * @return ChargeInterface
     */
    public function setPix(Money|int $amount): ChargeInterface
    {
        $this->sale['Payment'] = [
            'Type'   => PaymentTypeEnum::PIX->value,
            'Amount' => Money::asCentavos($amount),
        ];

        return $this;
    }

    /**
     * pay with boleto
     *
     * @param int $amount amount in cents
     * @param array<mixed> $options extra Payment fields, such as Demonstrative
     * @return ChargeInterface
     */
    public function setBoleto(Money|int $amount, array $options = []): ChargeInterface
    {
        $this->sale['Payment'] = array_merge([
            'Type'   => PaymentTypeEnum::BOLETO->value,
            'Amount' => Money::asCentavos($amount),
        ], $options);

        return $this;
    }

    /**
     * pay with a credit card
     *
     * @param int $amount amount in cents
     * @param array<mixed> $card
     * @param int $installments
     * @param bool $capture false authorizes only — capture later with capture()
     * @return ChargeInterface
     */
    public function setCreditCard(
        Money|int $amount,
        array $card,
        int $installments = 1,
        bool $capture = false
    ): ChargeInterface {
        $this->sale['Payment'] = [
            'Type'         => PaymentTypeEnum::CREDIT_CARD->value,
            'Amount'       => Money::asCentavos($amount),
            'Installments' => $installments,
            'Capture'      => $capture,
            'CreditCard'   => $card,
        ];

        return $this;
    }

    /**
     * set the idempotency key sent as RequestId.
     *
     * when not set, a random key is generated per create() call.
     *
     * @param string $requestId
     * @return ChargeInterface
     */
    public function setRequestId(string $requestId): ChargeInterface
    {
        $this->requestId = $requestId;

        return $this;
    }

    /**
     * create the sale
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        $this->sale['MerchantOrderId'] = $this->sale['MerchantOrderId'] ?? uniqid('phpay_');

        CieloSaleRequest::validate($this->sale);

        return $this->post('1/sales', $this->sale, [
            'RequestId' => $this->requestId ?? $this->generateRequestId(),
        ]);
    }

    /**
     * find a sale by its payment id
     *
     * @param string $paymentId
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $paymentId): array
    {
        return $this->queryGet("1/sales/{$paymentId}");
    }

    /**
     * find the sales of one order of your own system
     *
     * @param string $merchantOrderId
     * @return array<mixed>
     * @throws ApiException
     */
    public function findByOrderId(string $merchantOrderId): array
    {
        return $this->queryGet('1/sales', ['merchantOrderId' => $merchantOrderId]);
    }

    /**
     * get the status of a sale
     *
     * @param string $paymentId
     * @return int|null
     * @throws ApiException
     */
    public function getStatus(string $paymentId): ?int
    {
        $sale = $this->find($paymentId);

        $payment = $sale['Payment'] ?? null;

        if (!is_array($payment)) {
            return null;
        }

        $status = $payment['Status'] ?? null;

        return is_int($status) ? $status : null;
    }

    /**
     * get the Pix copy-and-paste code of a sale
     *
     * @param string $paymentId
     * @return string|null
     * @throws ApiException
     */
    public function getPixCode(string $paymentId): ?string
    {
        $sale = $this->find($paymentId);

        $payment = $sale['Payment'] ?? null;

        if (!is_array($payment)) {
            return null;
        }

        $code = $payment['QrCodeString'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * capture a previously authorized sale
     *
     * @param string $paymentId
     * @param int|null $amount amount in cents; null captures the full value
     * @return array<mixed>
     * @throws ApiException
     */
    public function capture(string $paymentId, Money|int|null $amount = null): array
    {
        $query = $amount === null ? '' : '?amount=' . Money::asCentavos($amount);

        return $this->put("1/sales/{$paymentId}/capture{$query}");
    }

    /**
     * cancel or refund a sale.
     *
     * the same endpoint undoes an authorization and refunds a captured sale —
     * what changes is the state the sale was in.
     *
     * @param string $paymentId
     * @param int|null $amount amount in cents; null cancels the full value
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $paymentId, Money|int|null $amount = null): array
    {
        $query = $amount === null ? '' : '?amount=' . Money::asCentavos($amount);

        return $this->put("1/sales/{$paymentId}/void{$query}");
    }

    /**
     * generate a RequestId for a write that must not be duplicated.
     *
     * @return string
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }
}
