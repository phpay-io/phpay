<?php

namespace PHPay\Asaas\Resources\Charge;

use GuzzleHttp\Client;
use PHPay\Asaas\Requests\AsaasChargeRequest;
use PHPay\Asaas\Resources\Charge\Interface\ChargeInterface;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Traits\HasAsaasClient;
use PHPay\Exceptions\{ApiException, ValidationException};

class Charge implements ChargeInterface
{
    /**
     * trait asaas client
     */
    use HasAsaasClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * @var array<mixed>
     */
    private array $charge = [];

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
        $this->client = $client ?? $this->clientAsaasBoot();
    }

    /**
     * set charge
     *
     * @param array<mixed> $charge
     * @return ChargeInterface
     */
    public function setCharge(array $charge): ChargeInterface
    {
        $this->charge = $charge;

        return $this;
    }

    /**
     * set query params
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
     * attach an existing gateway customer to the charge.
     *
     * @param string $customerId
     * @return ChargeInterface
     */
    public function setCustomerId(string $customerId): ChargeInterface
    {
        $this->charge['customer'] = $customerId;

        return $this;
    }

    /**
     * attach a customer to the charge.
     *
     * when the array carries an `id`, that customer is reused; otherwise a new
     * customer is created on the gateway. pass an id — or use setCustomerId() —
     * to avoid creating a duplicate customer on every charge.
     *
     * @param array<mixed> $customer
     * @return ChargeInterface
     * @throws ValidationException|ApiException
     */
    public function setCustomer(array $customer): ChargeInterface
    {
        if (isset($customer['id']) && is_string($customer['id']) && $customer['id'] !== '') {
            return $this->setCustomerId($customer['id']);
        }

        $created = (new Customer(
            $this->token,
            $customer,
            $this->sandbox,
            $this->client
        ))->create();

        if (!isset($created['id']) || !is_string($created['id'])) {
            throw new ApiException(
                'Asaas: a criação do cliente não retornou um id.',
                'Asaas',
                0,
                $created
            );
        }

        return $this->setCustomerId($created['id']);
    }

    /**
     * find charges by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("payments/{$id}");
    }

    /**
     * get all charges
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('payments', $this->queryParams);
    }

    /**
     * create charge
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see fields available in https://docs.asaas.com/reference/criar-nova-cobranca
     */
    public function create(): array
    {
        AsaasChargeRequest::validate($this->charge);

        return $this->post('payments', $this->charge);
    }

    /**
     * update charge
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws ApiException
     */
    public function update(string $id, array $data): array
    {
        return $this->put("payments/{$id}", $data);
    }

    /**
     * destroy charge
     *
     * @param string $id
     * @return bool
     * @throws ApiException
     */
    public function destroy(string $id): bool
    {
        return $this->delete("payments/{$id}");
    }

    /**
     * restore charge
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function restore(string $id): array
    {
        return $this->post("payments/{$id}/restore");
    }

    /**
     * get status charge
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function getStatus(string $id): array
    {
        return $this->get("payments/{$id}/status");
    }

    /**
     * get digitable line
     *
     * @param string $id
     * @return mixed
     * @throws ApiException
     */
    public function getDigitableLine(string $id): mixed
    {
        $charge = $this->get("payments/{$id}/identificationField");

        return $charge['identificationField'] ?? null;
    }

    /**
     * get qrcode pix
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function getQrCodePix(string $id): array
    {
        return $this->get("payments/{$id}/pixQrCode");
    }

    /**
     * confirm receipt
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws ApiException
     */
    public function confirmReceipt(string $id, array $data): array
    {
        return $this->post("payments/{$id}/receiveInCash", $data);
    }

    /**
     * undo confirm receipt
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function undoConfirmReceipt(string $id): array
    {
        return $this->post("payments/{$id}/undoReceivedInCash");
    }
}
