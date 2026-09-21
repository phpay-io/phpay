<?php

namespace PHPay\Efi\Resources\PixCharge;

use GuzzleHttp\Client;
use PHPay\Efi\Requests\{EfiPixChargeRequest, EfiPixDebtorRequest};
use PHPay\Efi\Resources\PixCharge\Interface\PixChargeInterface;
use PHPay\Efi\Traits\HasEfiPixClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;
use PHPay\Support\{Customer, Money};

/**
 * Pix charges of the Efí Pix API.
 *
 * two kinds, in the BACEN standard: immediate (`cob`), which expires in
 * seconds, and with due date (`cobv`), which carries fine, interest and
 * discount like a boleto. setDueDate() is what turns one into the other.
 *
 * the amount is a Money, and nothing else: this API wants reais where the
 * Cobranças API of the same gateway wants cents, so a raw number here would
 * be exactly the ambiguity Money exists to remove.
 */
class PixCharge implements PixChargeInterface
{
    /**
     * trait Efí Pix client
     */
    use HasEfiPixClient;

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
     * set the amount of the charge.
     *
     * @param Money $amount
     * @return PixChargeInterface
     */
    public function setAmount(Money $amount): PixChargeInterface
    {
        $this->charge['valor'] = ['original' => $amount->toDecimal()];

        return $this;
    }

    /**
     * set the Pix key that receives the payment — one registered in the
     * authenticated Efí account.
     *
     * @param string $key
     * @return PixChargeInterface
     */
    public function setKey(string $key): PixChargeInterface
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
     * @return PixChargeInterface
     * @throws ValidationException
     */
    public function setCustomer(Customer|array $customer): PixChargeInterface
    {
        if ($customer instanceof Customer) {
            $customer = EfiPixDebtorRequest::fromCustomer($customer);
        }

        EfiPixDebtorRequest::validate($customer);

        $this->charge['devedor'] = $customer;

        return $this;
    }

    /**
     * set the text shown to the payer (`solicitacaoPagador`).
     *
     * @param string $description
     * @return PixChargeInterface
     */
    public function setDescription(string $description): PixChargeInterface
    {
        $this->charge['solicitacaoPagador'] = $description;

        return $this;
    }

    /**
     * set how long an immediate charge stays payable. Efí defaults to 3600.
     *
     * replaces a due date set before: the last of the two wins.
     *
     * @param int $seconds
     * @return PixChargeInterface
     */
    public function setExpiration(int $seconds): PixChargeInterface
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
     * @return PixChargeInterface
     */
    public function setDueDate(string $date, int $validityAfterDue = 30): PixChargeInterface
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
     * @return PixChargeInterface
     */
    public function setAdditionalInfo(array $info): PixChargeInterface
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
     * @return PixChargeInterface
     */
    public function setQueryParams(array $queryParams): PixChargeInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * create the charge.
     *
     * immediate by default; with due date once setDueDate() is called. an
     * immediate charge without txid lets Efí generate one; a charge with due
     * date always needs a txid, so one is generated when none is given.
     *
     * @param string|null $txid your id for the charge, 26 to 35 letters and digits
     * @return array<mixed> with `txid` and `loc.id` — the QR Code comes from qrCode()
     * @throws ValidationException|ApiException
     */
    public function create(?string $txid = null): array
    {
        $calendar    = $this->charge['calendario'] ?? null;
        $withDueDate = is_array($calendar) && isset($calendar['dataDeVencimento']);

        EfiPixChargeRequest::validate($this->charge, $withDueDate, $txid);

        if ($withDueDate) {
            $txid ??= EfiPixChargeRequest::txid();

            return $this->put("v2/cobv/{$txid}", $this->charge);
        }

        return $txid === null
            ? $this->post('v2/cob', $this->charge)
            : $this->put("v2/cob/{$txid}", $this->charge);
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
        return $this->get("v2/cob/{$txid}");
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
        return $this->get("v2/cobv/{$txid}");
    }

    /**
     * list immediate charges
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('v2/cob', $this->listFilters());
    }

    /**
     * list charges with due date
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAllDue(): array
    {
        return $this->get('v2/cobv', $this->listFilters());
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
        return $this->patch("v2/cob/{$txid}", $data);
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
        return $this->patch("v2/cob/{$txid}", ['status' => self::REMOVED]);
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
        return $this->patch("v2/cobv/{$txid}", ['status' => self::REMOVED]);
    }

    /**
     * QR Code of a charge: `qrcode` (copy and paste), `imagemQrcode` (base64
     * PNG) and `linkVisualizacao`.
     *
     * @param int $locationId the `loc.id` that create() returns
     * @return array<mixed>
     * @throws ApiException
     */
    public function qrCode(int $locationId): array
    {
        return $this->get("v2/loc/{$locationId}/qrcode");
    }

    /**
     * refund a received Pix, fully or in part.
     *
     * @param string $endToEndId the `endToEndId` of the Pix received
     * @param Money $amount
     * @param string|null $refundId your id for the refund; generated when null
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $endToEndId, Money $amount, ?string $refundId = null): array
    {
        $refundId ??= EfiPixChargeRequest::txid();

        return $this->put("v2/pix/{$endToEndId}/devolucao/{$refundId}", [
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
        return $this->get("v2/pix/{$endToEndId}/devolucao/{$refundId}");
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
