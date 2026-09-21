<?php

namespace PHPay\Rede\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Rede\Enums\TransactionKindEnum;
use PHPay\Rede\Requests\RedeTransactionRequest;
use PHPay\Rede\Resources\Authorization\Authorization;
use PHPay\Rede\Resources\Charge\Interface\ChargeInterface;
use PHPay\Rede\Traits\HasRedeClient;

/**
 * transactions of the e.Rede v2 API.
 *
 * every request carries a Bearer token negotiated on a separate host, and
 * that token expires — the Authorization resource renegotiates when needed,
 * so this class just asks for one before each call.
 *
 * Amounts are integers in cents: R$ 20,99 is 2099.
 */
class Charge implements ChargeInterface
{
    /**
     * trait rede client
     */
    use HasRedeClient;

    /**
     * client guzzle, pointed at the transactions API
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $transaction = [];

    /**
     * construct
     *
     * @param Authorization $authorization
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private Authorization $authorization,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientRedeBoot();
    }

    /**
     * set the whole transaction payload
     *
     * @param array<mixed> $transaction
     * @return ChargeInterface
     */
    public function setTransaction(array $transaction): ChargeInterface
    {
        $this->transaction = $transaction;

        return $this;
    }

    /**
     * set the order identifier of your own system
     *
     * @param string $reference
     * @return ChargeInterface
     */
    public function setReference(string $reference): ChargeInterface
    {
        $this->transaction['reference'] = $reference;

        return $this;
    }

    /**
     * set the card being charged
     *
     * @param string $number
     * @param string $holderName
     * @param string $expirationMonth
     * @param string $expirationYear
     * @param string $securityCode
     * @return ChargeInterface
     */
    public function setCard(
        string $number,
        string $holderName,
        string $expirationMonth,
        string $expirationYear,
        string $securityCode
    ): ChargeInterface {
        $this->transaction['cardNumber']      = $number;
        $this->transaction['cardHolderName']  = $holderName;
        $this->transaction['expirationMonth'] = $expirationMonth;
        $this->transaction['expirationYear']  = $expirationYear;
        $this->transaction['securityCode']    = $securityCode;

        return $this;
    }

    /**
     * set how the card is charged
     *
     * @param int $amount amount in cents
     * @param TransactionKindEnum $kind
     * @param int $installments
     * @param bool $capture false authorizes only — capture later with capture()
     * @return ChargeInterface
     */
    public function setPayment(
        int $amount,
        TransactionKindEnum $kind = TransactionKindEnum::CREDIT,
        int $installments = 1,
        bool $capture = true
    ): ChargeInterface {
        $this->transaction['amount']       = $amount;
        $this->transaction['kind']         = $kind->value;
        $this->transaction['installments'] = $installments;
        $this->transaction['capture']      = $capture;

        return $this;
    }

    /**
     * set what shows on the cardholder statement
     *
     * @param string $softDescriptor
     * @return ChargeInterface
     */
    public function setSoftDescriptor(string $softDescriptor): ChargeInterface
    {
        $this->transaction['softDescriptor'] = $softDescriptor;

        return $this;
    }

    /**
     * create the transaction
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        $this->transaction['reference'] = $this->transaction['reference'] ?? uniqid('phpay_');

        RedeTransactionRequest::validate($this->transaction);

        return $this->request('POST', 'transactions', $this->authorized([
            'json' => $this->transaction,
        ]));
    }

    /**
     * find a transaction by its tid
     *
     * @param string $tid
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $tid): array
    {
        return $this->request('GET', "transactions/{$tid}", $this->authorized());
    }

    /**
     * find a transaction by the reference of your own system
     *
     * @param string $reference
     * @return array<mixed>
     * @throws ApiException
     */
    public function findByReference(string $reference): array
    {
        return $this->request('GET', 'transactions', $this->authorized([
            'query' => ['reference' => $reference],
        ]));
    }

    /**
     * get the return code of a transaction.
     *
     * "00" means approved — see TransactionStatusEnum::approved().
     *
     * @param string $tid
     * @return string|null
     * @throws ApiException
     */
    public function getStatus(string $tid): ?string
    {
        $transaction = $this->find($tid);

        $code = $transaction['returnCode'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * capture a previously authorized transaction
     *
     * @param string $tid
     * @param int|null $amount amount in cents; null captures the full value
     * @return array<mixed>
     * @throws ApiException
     */
    public function capture(string $tid, ?int $amount = null): array
    {
        return $this->request('PUT', "transactions/{$tid}", $this->authorized([
            'json' => $amount === null ? [] : ['amount' => $amount],
        ]));
    }

    /**
     * refund a transaction, fully or partially
     *
     * @param string $tid
     * @param int|null $amount amount in cents; null refunds the full value
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $tid, ?int $amount = null): array
    {
        return $this->request('POST', "transactions/{$tid}/refunds", $this->authorized([
            'json' => $amount === null ? [] : ['amount' => $amount],
        ]));
    }

    /**
     * add the Bearer token to the request options.
     *
     * asked for on every call: the Authorization resource hands back the
     * token it holds, and renegotiates only when that one has expired.
     *
     * @param array<mixed> $options
     * @return array<mixed>
     * @throws ApiException
     */
    private function authorized(array $options = []): array
    {
        $headers = $options['headers'] ?? [];

        $options['headers'] = array_merge(
            is_array($headers) ? $headers : [],
            ['Authorization' => 'Bearer ' . $this->authorization->token()]
        );

        return $options;
    }
}
