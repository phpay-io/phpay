<?php

namespace PHPay\Efi;

use Efi\Interface\EfiGatewayInterface;
use Efi\Resources\Authorization\Authorization;
use Efi\Resources\Charge\Charge;
use Efi\Traits\HasEfiClient;
use Exception;
use GuzzleHttp\Client;
use stdClass;

class EfiGateway implements EfiGatewayInterface
{
    use HasEfiClient;

    /**
     * client guzzle
     */
    public Client $client;

    /**
     * @var array<string> $token
     */
    private array $token;

    /**
     * construct
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param bool $sandbox
     */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private bool $sandbox = true,
    ) {
        $this->authorization();
    }

    /**
     * get token
     *
     * @return array<mixed> token
     */
    public function getToken(): array
    {
        return $this->token;
    }

    /**
     * create charge
     *
     * @param array<string> $charge
     * @return Charge
     */
    public function charge(array $charge = []): Charge
    {
        return new Charge(
            $this->token,
            $charge,
            $this->sandbox
        );
    }

    /**
     * create customer
     *
     * @param array<string> $customer
     * @return object customer
     */
    public function customer(array $customer = []): object
    {
        return new stdClass();
    }

    /**
     * create webhook
     *
     * @param array<string> $webhook
     * @return object webhook
     */
    public function webhook(array $webhook = []): object
    {
        return new stdClass();
    }

    /**
     * authorization
     *
     * @return void
     */
    private function authorization(): void
    {
        $authorization = new Authorization(
            $this->clientId,
            $this->clientSecret,
            $this->sandbox
        );

        $this->token = $authorization->getToken();

        if (!isset($this->token['access_token'])) {
            throw new \Exception('Token not generated');
        }
    }

    /**
     * create pix
     *
     * @param array<string> $pix
     * @return object pix
     */
    public function pix(array $pix = []): object
    {
        throw new Exception('Not implemented');
    }

    public function subscription(): object
    {
        throw new Exception('Not implemented');
    }
}
