<?php

namespace PHPay\Efi\Resources\Pix;

use GuzzleHttp\Client;
use PHPay\Efi\Resources\Pix\Interface\PixInterface;
use PHPay\Efi\Traits\HasEfiPixClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;

/**
 * Pix keys of the Efí Pix API.
 *
 * the API manages random keys (EVP) only: CPF, CNPJ, e-mail and phone keys
 * are registered in the Efí app.
 */
class Pix implements PixInterface
{
    /**
     * trait Efí Pix client
     */
    use HasEfiPixClient;

    /**
     * client guzzle
     */
    private Client $client;

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
     * create a random Pix key (EVP).
     *
     * the key comes back in `chave`.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function createKey(): array
    {
        // no body at all: the endpoint takes none
        return $this->request('POST', 'v2/gn/evp');
    }

    /**
     * list the random Pix keys of the account
     *
     * the keys come back in `chaves`.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('v2/gn/evp');
    }

    /**
     * remove a random Pix key
     *
     * @param string $key
     * @return bool
     * @throws ApiException
     */
    public function destroy(string $key): bool
    {
        return $this->delete('v2/gn/evp/' . rawurlencode($key));
    }
}
