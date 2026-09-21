<?php

namespace PHPay\Efi\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Efi\Enums\{AccountTypeEnum, PeriodicityEnum};
use PHPay\Efi\Requests\{EfiPixDebtorRequest, EfiSubscriptionRequest};
use PHPay\Efi\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\Efi\Traits\HasEfiPixClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;
use PHPay\Support\{Customer, Money};

/**
 * Pix Automático of the Efí Pix API.
 *
 * the payer authorizes once, in the bank app, and every cycle is debited
 * without a new approval. two objects, in the BACEN standard:
 *
 * - the recurrence (`rec`) — the authorization: contract, debtor, calendar
 *   and amount. create(), find(), update(), cancel()
 * - the charge of each cycle (`cobr`), which the receiver creates within the
 *   recurrence. createCharge(), findCharge(), cancelCharge()
 *
 * the payer authorizes through a QR Code: createLocation() gives the
 * location, setLocation() binds it before create(), and find() brings the
 * copy-and-paste code in `dadosQR`. setActivationTxid() does the same in one
 * payment, by riding on an immediate charge.
 */
class Subscription implements SubscriptionInterface
{
    /**
     * trait Efí Pix client
     */
    use HasEfiPixClient;

    /**
     * status the BACEN standard uses to cancel a recurrence and a charge
     */
    private const CANCELLED = 'CANCELADA';

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * top-level fields of the recurrence
     *
     * @var array<string, mixed>
     */
    private array $subscription = [];

    /**
     * the `vinculo`: contract, debtor and object
     *
     * @var array<string, mixed>
     */
    private array $bond = [];

    /**
     * the `valor`: fixed or minimum amount
     *
     * @var array<string, string>
     */
    private array $amount = [];

    /**
     * @var array<mixed>
     */
    private array $receiver = [];

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * construct
     *
     * @param array<string, mixed> $token
     * @param Certificate|null $certificate required unless a client is injected
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     * @throws ValidationException
     */
    public function __construct(
        array $token,
        ?Certificate $certificate = null,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientEfiPixBoot($token, $certificate);
    }

    /**
     * set the debtor
     *
     * @param Customer|array<mixed> $customer a Customer, or `cpf`/`cnpj` and `nome`
     * @return SubscriptionInterface
     * @throws ValidationException
     */
    public function setCustomer(Customer|array $customer): SubscriptionInterface
    {
        if ($customer instanceof Customer) {
            $customer = EfiPixDebtorRequest::fromCustomer($customer);
        }

        EfiPixDebtorRequest::validate($customer);

        $this->bond['devedor'] = $customer;

        return $this;
    }

    /**
     * set the contract the recurrence is bound to — your id for it, shown to
     * the payer. up to 35 characters.
     *
     * @param string $contract
     * @return SubscriptionInterface
     */
    public function setContract(string $contract): SubscriptionInterface
    {
        $this->bond['contrato'] = $contract;

        return $this;
    }

    /**
     * set what is being charged (`objeto`), up to 35 characters.
     *
     * @param string $description
     * @return SubscriptionInterface
     */
    public function setDescription(string $description): SubscriptionInterface
    {
        $this->bond['objeto'] = $description;

        return $this;
    }

    /**
     * set a fixed amount for every cycle
     *
     * @param Money $amount
     * @return SubscriptionInterface
     */
    public function setAmount(Money $amount): SubscriptionInterface
    {
        $this->amount['valorRec'] = $amount->toDecimal();

        return $this;
    }

    /**
     * set the minimum of a variable amount — each charge brings its own.
     *
     * @param Money $amount
     * @return SubscriptionInterface
     */
    public function setMinimumAmount(Money $amount): SubscriptionInterface
    {
        $this->amount['valorMinimoRecebedor'] = $amount->toDecimal();

        return $this;
    }

    /**
     * set the calendar of the recurrence
     *
     * @param PeriodicityEnum $periodicity
     * @param string $startDate Y-m-d
     * @param string|null $endDate Y-m-d; null for no end
     * @return SubscriptionInterface
     */
    public function setPeriodicity(
        PeriodicityEnum $periodicity,
        string $startDate,
        ?string $endDate = null
    ): SubscriptionInterface {
        $this->subscription['calendario'] = array_filter([
            'dataInicial'   => $startDate,
            'dataFinal'     => $endDate,
            'periodicidade' => $periodicity->value,
        ], static fn (?string $value): bool => $value !== null);

        return $this;
    }

    /**
     * allow retries after a failed charge — up to 3 in 7 days. off by default.
     *
     * @param bool $allow
     * @return SubscriptionInterface
     */
    public function allowRetries(bool $allow = true): SubscriptionInterface
    {
        $this->subscription['politicaRetentativa'] = $allow ? 'PERMITE_3R_7D' : 'NAO_PERMITE';

        return $this;
    }

    /**
     * bind the recurrence to a location, for the QR Code journey.
     *
     * @param int $locationId the `id` that createLocation() returns
     * @return SubscriptionInterface
     */
    public function setLocation(int $locationId): SubscriptionInterface
    {
        $this->subscription['loc'] = $locationId;

        return $this;
    }

    /**
     * activate the recurrence together with an immediate charge: the payer
     * pays the first cycle and authorizes the next ones in one QR Code.
     *
     * @param string $txid of an immediate charge created with pixCharge()
     * @return SubscriptionInterface
     */
    public function setActivationTxid(string $txid): SubscriptionInterface
    {
        $this->subscription['ativacao'] = ['dadosJornada' => ['txid' => $txid]];

        return $this;
    }

    /**
     * set the account that receives the charges of createCharge().
     *
     * @param string $account account number, with digit
     * @param AccountTypeEnum $type
     * @param string|null $branch agency, when the account has one
     * @return SubscriptionInterface
     */
    public function setReceiver(
        string $account,
        AccountTypeEnum $type = AccountTypeEnum::CHECKING,
        ?string $branch = null
    ): SubscriptionInterface {
        $this->receiver = array_filter([
            'agencia'   => $branch,
            'conta'     => $account,
            'tipoConta' => $type->value,
        ], static fn (?string $value): bool => $value !== null);

        return $this;
    }

    /**
     * set list filters. `inicio` and `fim` default to the last 30 days.
     *
     * @param array<mixed> $queryParams
     * @return SubscriptionInterface
     */
    public function setQueryParams(array $queryParams): SubscriptionInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * create the recurrence
     *
     * @return array<mixed> with `idRec`
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        $recurrence = array_filter([
            'vinculo' => $this->bond,
            'valor'   => $this->amount,
        ]) + $this->subscription + ['politicaRetentativa' => 'NAO_PERMITE'];

        EfiSubscriptionRequest::validate($recurrence);

        return $this->post('v2/rec', $recurrence);
    }

    /**
     * find a recurrence — with its status and, when bound to a location, the
     * copy-and-paste code in `dadosQR`.
     *
     * @param string $id the `idRec`
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("v2/rec/{$id}");
    }

    /**
     * list recurrences
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('v2/rec', $this->queryParams + [
            'inicio' => gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days')),
            'fim'    => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * revise a recurrence.
     *
     * the payload goes as is: an amount here must already be a decimal
     * string — use Money::toDecimal().
     *
     * @param string $id the `idRec`
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws ApiException
     */
    public function update(string $id, array $data): array
    {
        return $this->patch("v2/rec/{$id}", $data);
    }

    /**
     * cancel a recurrence
     *
     * @param string $id the `idRec`
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $id): array
    {
        return $this->patch("v2/rec/{$id}", ['status' => self::CANCELLED]);
    }

    /**
     * create a location for the QR Code journey
     *
     * @return array<mixed> with `id`, for setLocation()
     * @throws ApiException
     */
    public function createLocation(): array
    {
        // no body at all: the endpoint takes none
        return $this->request('POST', 'v2/locrec');
    }

    /**
     * create the charge of one cycle.
     *
     * @param string $id the `idRec`
     * @param Money $amount
     * @param string $dueDate Y-m-d
     * @param array<mixed> $extra other fields, e.g. `infoAdicional` or `ajusteDiaUtil`
     * @return array<mixed> with `txid`
     * @throws ValidationException|ApiException
     */
    public function createCharge(string $id, Money $amount, string $dueDate, array $extra = []): array
    {
        $charge = array_replace([
            'idRec'         => $id,
            'calendario'    => ['dataDeVencimento' => $dueDate],
            'valor'         => ['original' => $amount->toDecimal()],
            'ajusteDiaUtil' => true,
            'recebedor'     => $this->receiver,
        ], $extra);

        EfiSubscriptionRequest::validateCharge($charge);

        return $this->post('v2/cobr', $charge);
    }

    /**
     * find the charge of a cycle
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function findCharge(string $txid): array
    {
        return $this->get("v2/cobr/{$txid}");
    }

    /**
     * cancel the charge of a cycle — only before the day of its first
     * settlement attempt.
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancelCharge(string $txid): array
    {
        return $this->patch("v2/cobr/{$txid}", ['status' => self::CANCELLED]);
    }
}
