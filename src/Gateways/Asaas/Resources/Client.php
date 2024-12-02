<?php

namespace PHPay\Gateways\Asaas\Resources;

use Illuminate\Support\Facades\Http;
use PHPay\Exceptions\AsaasExceptions;
use PHPay\Gateways\Asaas\Requests\AsaasClientRequest;
use PHPay\Gateways\Asaas\Resources\Interfaces\ClientInterface;
use PHPay\Gateways\Gateway;

final class Client implements ClientInterface
{
    /**
     * @var array
     */
    protected array $client;

    /**
     * @var array
     */
    protected array $filters = [];

    /**
     * @var Gateway
     */
    protected Gateway $gateway;

    /**
     * construct
     *
     * @param array $client
     * @param Gateway $gateway
     */
    public function __construct(array $client, Gateway $gateway)
    {
        $this->client = $client;
        $this->gateway = $gateway;
    }

    /**
     * get all clients
     *
     * @return array
     */
    public function all(): array
    {
        $client = Http::asaas()
            ->get(env('ASSAS_CLIENTS') . '/?' . http_build_query($this->filters))
            ->json();

        return $client;
    }

    /**
     * get client
     *
     * @return array
     */
    public function get(): array
    {
        $client = Http::asaas()
            ->get(env('ASSAS_CLIENTS') . '/?' . http_build_query($this->filters))
            ->json();

        return $client['data'][0];
    }

    /**
     * check if client exists
     *
     * @param string $client
     * @return array
     */
    public function find(string $cpfCnpj): array
    {
        $client = (object) Http::asaas()
            ->get(env('ASSAS_CLIENTS'), [
                'cpfCnpj' => $cpfCnpj,
            ])->json();

        return $client->data;
    }

    /**
     * set filters
     * to ready about filters, see
     * https://docs.asaas.com/reference/listar-clientes
     *
     * @param array $filter
     * @return $this
     */
    public function with(array $filter): self
    {
        $this->filters = $filter;

        return $this;
    }

    /**
     * store client
     *
     * @return $this
     */
    public function store(): string|AsaasExceptions
    {
        AsaasClientRequest::validate($this->client);

        try {
            extract($this->client);

            $client = self::find($cpf_cnpj);

            if (! empty($client)) {
                return  $client[0]['id'];
            }

            $client = Http::asaas()
                ->post(env('ASSAS_CLIENTS'), [
                    'name' => $name,
                    'cpfCnpj' => $cpf_cnpj,
                ])->json();

            if (isset($client['errors'])) {
                return (new AsaasExceptions())($client['errors'][0]['description']);
            }

            return $client['id'];

        } catch (\Exception $e) {
            return (new AsaasExceptions())($e->getMessage());
        }
    }

    /**
     * delete client
     *
     * @return bool
     */
    public function delete(): bool
    {
        try {
            $client = $this->find($this->client['cpf_cnpj']);

            if (empty($client)) {
                return false;
            }

            $client = Http::asaas()
                ->delete(env('ASSAS_CLIENTS') . '/' . $client[0]['id'])
                ->json();

            if ($client['deleted']) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            (new AsaasExceptions())($e->getMessage());

            return false;
        }
    }

    /**
     * restore client
     *
     * @param string $id
     * @return bool
     */
    public function restore(string $id): bool
    {
        try {
            $client = Http::asaas()
                ->post(str_replace('{id}', $id, env('ASSAS_CLIENTS_RESTORE')))
                ->json();

            if ($client) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            (new AsaasExceptions())($e->getMessage());

            return false;
        }
    }

    /**
     * get client notifications
     *
     * @param string $id
     * @return array
     */
    public function notifications(string $id): ?array
    {
        $notifications = Http::asaas()
            ->get(str_replace('{id}', $id, env('ASSAS_CLIENTS_NOTIFICATIONS')))
            ->json();

        return $notifications;
    }
}
