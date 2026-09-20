<?php

namespace PHPay\Asaas\Resources\Pix;

use GuzzleHttp\Client;
use PHPay\Asaas\Resources\Pix\Interface\PixInterface;
use PHPay\Asaas\Traits\HasAsaasClient;

class Pix implements PixInterface
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
    * set query params
    *
    * @param array<mixed> $queryParams
    * @return PixInterface
    */
    public function setQueryParams(array $queryParams): PixInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * create pix key
     *
     * @return array<mixed>
     * @throws \PHPay\Exceptions\ApiException
     */
    public function createKey(): array
    {
        return $this->post('pix/addressKeys', [
            'type' => 'EVP',
        ]);
    }

    /**
     * find pix key
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array
    {
        return $this->get("pix/addressKeys/{$id}");
    }

    /**
     * get all pix keys
     *
     * @return array<mixed>
     * @see params in https://docs.asaas.com/reference/list-keys
     */
    public function getAll(): array
    {
        $params = $this->queryParams;

        if (empty($params)) {
            $params = [
                'offset' => 0,
                'limit'  => 100,
            ];
        }

        return $this->get('pix/addressKeys', $params);
    }

    /**
     * destroy pix key
     *
     * @param string $id
     * @return bool
     */
    public function destroy(string $id): bool
    {
        return $this->delete("pix/addressKeys/{$id}");
    }

    /**
     * static qr code
     *
     * @param array<mixed> $params
     * @return array<mixed>
     * @see params in https://docs.asaas.com/reference/create-static-qrcode
     */
    public function staticQrCode(array $params): array
    {
        return $this->post('pix/qrCodes/static', $params);
    }

    /**
     * destroy static qr code
     *
     * @param string $id
     * @return bool
     */
    public function destroyStaticQrCode(string $id): bool
    {
        return $this->delete("pix/qrCodes/static/{$id}");
    }
}
