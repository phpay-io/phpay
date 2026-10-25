<?php

namespace PHPay\Inter\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;
use PHPay\Inter\Requests\{InterPixChargeRequest, InterPixDebtorRequest};
use PHPay\Inter\Resources\Charge\Interface\ChargeInterface;
use PHPay\Inter\Traits\HasInterClient;
use PHPay\Support\{Customer, Money};

/**
 * Pix charges of the Inter Pix API.
 *
 * two kinds, in the BACEN standard: immediate (`cob`), which expires in
 * seconds, and with due date (`cobv`), which carries fine, interest and
 * discount like a boleto. setDueDate() is what turns one into the other.
 *
 * **this is `charge()` on the Inter gateway, and the Pix charge is what it
 * returns.** On Efí `charge()` is the boleto and `pixCharge()` is this, because
 * both APIs were already there; the Inter boleto lives in a separate API
 * (`/cobranca/v3`) that this version does not speak, and making anyone write
 * `pixCharge()` on a gateway whose only charge is Pix would be ceremony.
 *
 * the amount is a Money, and nothing else: this API wants reais as a decimal
 * string, which is exactly the ambiguity Money exists to remove.
 *
 * there is no `qrCode()` here, unlike the Efí resource: Inter does not expose
 * `GET /loc/{id}/qrcode`. The copy-and-paste code comes back on the charge
 * itself, in `pixCopiaECola`.
 */
class Charge implements ChargeInterface
{
    /**
     * trait Inter client
     */
    use HasInterClient;
    /**
     * path prefix of the Pix API, under the single Inter host
     */
    private const PIX = 'pix/v2/';

    /**
     * status the BACEN standard uses to remove a charge
     */
    private const REMOVED = 'REMOVIDA_PELO_USUARIO_RECEBEDOR';

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
     * @param array<string, mixed> $token
     * @param Certificate|null $certificate required unless a client is injected
     * @param bool $sandbox
     * @param string|null $account `x-conta-corrente`
     * @param Client|null $client injected http client, mainly for tests
     * @throws ValidationException
     */
    public function __construct(
        #[\SensitiveParameter]
        array $token,
        ?Certificate $certificate = null,
        protected bool $sandbox = true,
        ?string $account = null,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientInterBoot($token, $certificate, $account);
    }

    /**
     * set the amount of the charge.
     *
     * @param Money $amount
     * @return ChargeInterface
     */
    public function setAmount(Money $amount): ChargeInterface
    {
        $this->charge['valor'] = ['original' => $amount->toDecimal()];

        return $this;
    }

    /**
     * set the Pix key that receives the payment — one registered to the
     * authenticated Inter account.
     *
     * @param string $key
     * @return ChargeInterface
     */
    public function setKey(string $key): ChargeInterface
    {
        $this->charge['chave'] = $key;

        return $this;
    }

    /**
     * set the debtor.
     *
     * optional in an immediate charge, required in one with due date.
     *
     * @param Customer|array<mixed> $customer a Customer, or `cpf`/`cnpj` and `nome`
     * @return ChargeInterface
     * @throws ValidationException
     */
    public function setCustomer(Customer|array $customer): ChargeInterface
    {
        if ($customer instanceof Customer) {
            $customer = InterPixDebtorRequest::fromCustomer($customer);
        }

        InterPixDebtorRequest::validate($customer);

        $this->charge['devedor'] = $customer;

        return $this;
    }

    /**
     * set the text shown to the payer (`solicitacaoPagador`).
     *
     * @param string $description
     * @return ChargeInterface
     */
    public function setDescription(string $description): ChargeInterface
    {
        $this->charge['solicitacaoPagador'] = $description;

        return $this;
    }

    /**
     * set how long an immediate charge stays payable.
     *
     * replaces a due date set before: the last of the two wins.
     *
     * @param int $seconds
     * @return ChargeInterface
     */
    public function setExpiration(int $seconds): ChargeInterface
    {
        $this->charge['calendario'] = ['expiracao' => $seconds];

        return $this;
    }

    /**
     * turn the charge into a charge with due date (`cobv`).
     *
     * replaces an expiration set before: the last of the two wins.
     *
     * @param string $date Y-m-d
     * @param int $validityAfterDue days it stays payable after the due date
     * @return ChargeInterface
     */
    public function setDueDate(string $date, int $validityAfterDue = 30): ChargeInterface
    {
        $this->charge['calendario'] = [
            'dataDeVencimento'       => $date,
            'validadeAposVencimento' => $validityAfterDue,
        ];

        return $this;
    }

    /**
     * set additional information shown to the payer.
     *
     * @param array<string, string> $info label => value, e.g. ['Pedido' => '1234']
     * @return ChargeInterface
     */
    public function setAdditionalInfo(array $info): ChargeInterface
    {
        $this->charge['infoAdicionais'] = array_map(
            static fn (string $name, string $value): array => ['nome' => $name, 'valor' => $value],
            array_keys($info),
            array_values($info),
        );

        return $this;
    }

    /**
     * set list filters. `inicio` and `fim` default to the last 30 days.
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
     * create the charge.
     *
     * immediate by default; with due date once setDueDate() is called. an
     * immediate charge without txid lets Inter generate one; a charge with due
     * date always needs a txid, so one is generated when none is given.
     *
     * @param string|null $txid your id for the charge, 26 to 35 letters and digits
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(?string $txid = null): array
    {
        $calendar    = $this->charge['calendario'] ?? null;
        $withDueDate = is_array($calendar) && isset($calendar['dataDeVencimento']);

        InterPixChargeRequest::validate($this->charge, $withDueDate, $txid);

        if ($withDueDate) {
            $txid ??= InterPixChargeRequest::txid();

            return $this->put(self::PIX . "cobv/{$txid}", $this->charge);
        }

        return $txid === null
            ? $this->post(self::PIX . 'cob', $this->charge)
            : $this->put(self::PIX . "cob/{$txid}", $this->charge);
    }

    /**
     * find an immediate charge
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $txid): array
    {
        return $this->get(self::PIX . "cob/{$txid}");
    }

    /**
     * find a charge with due date
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function findDue(string $txid): array
    {
        return $this->get(self::PIX . "cobv/{$txid}");
    }

    /**
     * list immediate charges
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get(self::PIX . 'cob', $this->listFilters());
    }

    /**
     * list charges with due date
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAllDue(): array
    {
        return $this->get(self::PIX . 'cobv', $this->listFilters());
    }

    /**
     * revise an immediate charge.
     *
     * the payload goes as is: an amount here must already be a decimal
     * string — use Money::toDecimal().
     *
     * @param string $txid
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws ApiException
     */
    public function update(string $txid, array $data): array
    {
        return $this->patch(self::PIX . "cob/{$txid}", $data);
    }

    /**
     * cancel an immediate charge
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $txid): array
    {
        return $this->patch(self::PIX . "cob/{$txid}", ['status' => self::REMOVED]);
    }

    /**
     * cancel a charge with due date
     *
     * @param string $txid
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancelDue(string $txid): array
    {
        return $this->patch(self::PIX . "cobv/{$txid}", ['status' => self::REMOVED]);
    }

    /**
     * find a received Pix by its endToEndId
     *
     * @param string $endToEndId
     * @return array<mixed>
     * @throws ApiException
     */
    public function findPix(string $endToEndId): array
    {
        return $this->get(self::PIX . "pix/{$endToEndId}");
    }

    /**
     * list received Pix. `inicio` and `fim` default to the last 30 days.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAllPix(): array
    {
        return $this->get(self::PIX . 'pix', $this->listFilters());
    }

    /**
     * refund a received Pix, fully or in part.
     *
     * **this is not `SupportsRefunds`**, and the gateway does not declare that
     * capability. The amount is required here — the BACEN schema demands
     * `valor` — while the capability promises that an absent amount refunds
     * everything, and the refund is addressed by the `endToEndId` of the
     * payment rather than by the charge. Same reason the Efí gateway keeps its
     * refund on the resource.
     *
     * @param string $endToEndId the `endToEndId` of the Pix received
     * @param Money $amount
     * @param string|null $refundId your id for the refund; generated when null
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $endToEndId, Money $amount, ?string $refundId = null): array
    {
        $refundId ??= InterPixChargeRequest::txid();

        return $this->put(self::PIX . "pix/{$endToEndId}/devolucao/{$refundId}", [
            'valor' => $amount->toDecimal(),
        ]);
    }

    /**
     * find a refund
     *
     * @param string $endToEndId
     * @param string $refundId
     * @return array<mixed>
     * @throws ApiException
     */
    public function findRefund(string $endToEndId, string $refundId): array
    {
        return $this->get(self::PIX . "pix/{$endToEndId}/devolucao/{$refundId}");
    }

    /**
     * list filters, with the required period defaulting to the last 30 days.
     *
     * @return array<mixed>
     */
    private function listFilters(): array
    {
        return $this->queryParams + [
            'inicio' => gmdate('Y-m-d\TH:i:s\Z', strtotime('-30 days')),
            'fim'    => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }
}
