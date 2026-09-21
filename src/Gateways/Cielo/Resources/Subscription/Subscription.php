<?php

namespace PHPay\Cielo\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Cielo\Enums\{PaymentTypeEnum, RecurrentIntervalEnum};
use PHPay\Cielo\Requests\CieloRecurrentRequest;
use PHPay\Cielo\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\Cielo\Traits\HasCieloClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\Money;

/**
 * recurrences of the Cielo E-commerce API 3.0.
 *
 * Cielo has no endpoint that creates a subscription: a recurrence is born
 * from a sale carrying a RecurrentPayment block, and only then gets a
 * RecurrentPaymentId of its own to manage. It always charges a credit card.
 */
class Subscription implements SubscriptionInterface
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
     * @var array<mixed>
     */
    private array $card = [];

    /**
     * @var array<mixed>
     */
    private array $recurrent = [];

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
     * set the order identifier of your own system
     *
     * @param string $merchantOrderId
     * @return SubscriptionInterface
     */
    public function setOrderId(string $merchantOrderId): SubscriptionInterface
    {
        $this->sale['MerchantOrderId'] = $merchantOrderId;

        return $this;
    }

    /**
     * set the customer of the recurrence
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(array $customer): SubscriptionInterface
    {
        $this->sale['Customer'] = $customer;

        return $this;
    }

    /**
     * set the credit card the recurrence charges
     *
     * @param array<mixed> $card
     * @return SubscriptionInterface
     */
    public function setCard(array $card): SubscriptionInterface
    {
        $this->card = $card;

        return $this;
    }

    /**
     * set how often the recurrence charges
     *
     * @param RecurrentIntervalEnum $interval
     * @return SubscriptionInterface
     */
    public function setInterval(RecurrentIntervalEnum $interval): SubscriptionInterface
    {
        $this->recurrent['Interval'] = $interval->value;

        return $this;
    }

    /**
     * set when the recurrence stops
     *
     * @param string $endDate
     * @return SubscriptionInterface
     */
    public function setEndDate(string $endDate): SubscriptionInterface
    {
        $this->recurrent['EndDate'] = $endDate;

        return $this;
    }

    /**
     * create the recurrence
     *
     * @param int $amount amount in cents
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(Money|int $amount): array
    {
        $this->sale['MerchantOrderId'] = $this->sale['MerchantOrderId'] ?? uniqid('phpay_');

        $recurrent = $this->recurrent;

        $recurrent['AuthorizeNow'] = $recurrent['AuthorizeNow'] ?? true;
        $recurrent['Interval']     = $recurrent['Interval'] ?? RecurrentIntervalEnum::MONTHLY->value;

        $this->sale['Payment'] = [
            'Type'             => PaymentTypeEnum::CREDIT_CARD->value,
            'Amount'           => Money::asCentavos($amount),
            'Installments'     => 1,
            'CreditCard'       => $this->card,
            'RecurrentPayment' => $recurrent,
        ];

        CieloRecurrentRequest::validate($this->sale);

        return $this->post('1/sales', $this->sale);
    }

    /**
     * find a recurrence by id
     *
     * @param string $recurrentPaymentId
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $recurrentPaymentId): array
    {
        return $this->queryGet("1/RecurrentPayment/{$recurrentPaymentId}");
    }

    /**
     * suspend a recurrence
     *
     * @param string $recurrentPaymentId
     * @return array<mixed>
     * @throws ApiException
     */
    public function deactivate(string $recurrentPaymentId): array
    {
        return $this->put("1/RecurrentPayment/{$recurrentPaymentId}/Deactivate");
    }

    /**
     * resume a suspended recurrence
     *
     * @param string $recurrentPaymentId
     * @return array<mixed>
     * @throws ApiException
     */
    public function reactivate(string $recurrentPaymentId): array
    {
        return $this->put("1/RecurrentPayment/{$recurrentPaymentId}/Reactivate");
    }

    /**
     * change the charged amount
     *
     * @param string $recurrentPaymentId
     * @param int $amount amount in cents
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function updateAmount(string $recurrentPaymentId, Money|int $amount): array
    {
        $centavos = Money::asCentavos($amount);

        CieloRecurrentRequest::validateAmount($centavos);

        return $this->putValue("1/RecurrentPayment/{$recurrentPaymentId}/Amount", $centavos);
    }

    /**
     * change how often the recurrence charges
     *
     * @param string $recurrentPaymentId
     * @param RecurrentIntervalEnum $interval
     * @return array<mixed>
     * @throws ApiException
     */
    public function updateInterval(string $recurrentPaymentId, RecurrentIntervalEnum $interval): array
    {
        return $this->putValue("1/RecurrentPayment/{$recurrentPaymentId}/Interval", $interval->value);
    }

    /**
     * change when the recurrence stops
     *
     * @param string $recurrentPaymentId
     * @param string $endDate
     * @return array<mixed>
     * @throws ApiException
     */
    public function updateEndDate(string $recurrentPaymentId, string $endDate): array
    {
        return $this->putValue("1/RecurrentPayment/{$recurrentPaymentId}/EndDate", $endDate);
    }

    /**
     * change the date of the next charge
     *
     * @param string $recurrentPaymentId
     * @param string $nextPaymentDate
     * @return array<mixed>
     * @throws ApiException
     */
    public function updateNextPaymentDate(string $recurrentPaymentId, string $nextPaymentDate): array
    {
        return $this->putValue(
            "1/RecurrentPayment/{$recurrentPaymentId}/NextPaymentDate",
            $nextPaymentDate
        );
    }

    /**
     * send a PUT whose body is a bare JSON value.
     *
     * the recurrence update endpoints do not take an object — they take the
     * new value on its own, such as `15700` or `"Monthly"`.
     *
     * @param string $endpoint
     * @param string|int $value
     * @return array<mixed>
     * @throws ApiException
     */
    private function putValue(string $endpoint, string|int $value): array
    {
        return $this->request('PUT', $endpoint, ['json' => $value]);
    }
}
