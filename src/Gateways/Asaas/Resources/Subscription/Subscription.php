<?php

namespace PHPay\Asaas\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Asaas\Enums\SubscriptionCycleEnum;
use PHPay\Asaas\Requests\AsaasCustomerRequest;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\Asaas\Resources\Subscription\Requests\{
    StoreSubscriptionAsaasRequest,
    SubscriptionCreditCardAsaasRequest,
    SubscriptionInvoiceSettingsAsaasRequest
};
use PHPay\Asaas\Traits\HasAsaasClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{Customer as CustomerData, Money};

/**
 * subscriptions of the Asaas API.
 *
 * the subscription generates one charge per cycle; each of them is a regular
 * charge, listed by getPayments() and handled by the charge resource. an
 * invoice (nota fiscal) can be issued for every charge automatically, once
 * createInvoiceSettings() is called.
 */
class Subscription implements SubscriptionInterface
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
     * gateway customer the subscription belongs to
     */
    private ?string $customerId = null;

    /**
     * @var array<mixed>
     */
    private array $subscription = [];

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
     * attach an existing gateway customer to the subscription.
     *
     * @param string $customerId
     * @return SubscriptionInterface
     */
    public function setCustomerId(string $customerId): SubscriptionInterface
    {
        $this->customerId = $customerId;

        return $this;
    }

    /**
     * attach a customer to the subscription.
     *
     * when the array carries an `id`, that customer is reused; otherwise a new
     * customer is created on the gateway. pass an id — or use setCustomerId() —
     * to avoid creating a duplicate customer on every subscription.
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     * @throws ValidationException|ApiException
     */
    public function setCustomer(CustomerData|array $customer): SubscriptionInterface
    {
        if ($customer instanceof CustomerData) {
            if ($customer->id !== null) {
                return $this->setCustomerId($customer->id);
            }

            $customer = AsaasCustomerRequest::fromCustomer($customer);
        }

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
     * set the payload of the subscription.
     *
     * @param array<mixed> $subscription
     * @return SubscriptionInterface
     * @see fields available in https://docs.asaas.com/reference/criar-nova-assinatura
     */
    public function setSubscription(array $subscription): SubscriptionInterface
    {
        $this->subscription = array_replace($this->subscription, $subscription);

        return $this;
    }

    /**
     * set the amount of each charge.
     *
     * Asaas takes reais as a decimal — pass a Money and the unit is handled
     * for you, or a raw number, which is read as reais.
     *
     * @param Money|int|float $amount
     * @return SubscriptionInterface
     */
    public function setAmount(Money|int|float $amount): SubscriptionInterface
    {
        $this->subscription['value'] = Money::asReais($amount);

        return $this;
    }

    /**
     * set how often a charge is generated
     *
     * @param SubscriptionCycleEnum $cycle
     * @return SubscriptionInterface
     */
    public function setCycle(SubscriptionCycleEnum $cycle): SubscriptionInterface
    {
        $this->subscription['cycle'] = $cycle->value;

        return $this;
    }

    /**
     * set list filters
     *
     * @param array<mixed> $queryParams
     * @return SubscriptionInterface
     * @see params in https://docs.asaas.com/reference/listar-assinaturas
     */
    public function setQueryParams(array $queryParams): SubscriptionInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    /**
     * create subscription
     *
     * the payload is what the setters built, overridden by the array given
     * here — so create([...]) alone keeps working as it always did.
     *
     * @param array<mixed> $subscription
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see fields available in https://docs.asaas.com/reference/criar-nova-assinatura
     */
    public function create(array $subscription = []): array
    {
        $subscription = array_replace($this->subscription, $subscription);

        $subscription['customer'] = $subscription['customer'] ?? $this->customerId;

        StoreSubscriptionAsaasRequest::validate($subscription);

        return $this->post('subscriptions', $subscription);
    }

    /**
     * list subscriptions
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('subscriptions', $this->queryParams);
    }

    /**
     * find a subscription
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("subscriptions/{$id}");
    }

    /**
     * update a subscription.
     *
     * changes apply to the charges still to be generated; send
     * `updatePendingPayments: true` to apply them to the pending ones too.
     *
     * @param string $id
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws ApiException
     * @see fields available in https://docs.asaas.com/reference/atualizar-assinatura-existente
     */
    public function update(string $id, array $data): array
    {
        return $this->put("subscriptions/{$id}", $data);
    }

    /**
     * stop generating charges, keeping the existing ones.
     *
     * the pause Asaas recommends instead of destroy(), which also removes the
     * pending and overdue charges.
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function deactivate(string $id): array
    {
        return $this->update($id, ['status' => 'INACTIVE']);
    }

    /**
     * generate charges again.
     *
     * Asaas requires a new due date to reactivate — the old one is likely
     * already in the past.
     *
     * @param string $id
     * @param string $nextDueDate Y-m-d of the next charge
     * @return array<mixed>
     * @throws ApiException
     */
    public function reactivate(string $id, string $nextDueDate): array
    {
        return $this->update($id, [
            'status'      => 'ACTIVE',
            'nextDueDate' => $nextDueDate,
        ]);
    }

    /**
     * remove a subscription.
     *
     * the pending and overdue charges go with it; paid ones stay. to only
     * pause, use deactivate().
     *
     * @param string $id
     * @return bool
     * @throws ApiException
     */
    public function destroy(string $id): bool
    {
        return $this->delete("subscriptions/{$id}");
    }

    /**
     * replace the card of a subscription without charging it.
     *
     * the pending charges move to the new card as well.
     *
     * @param string $id
     * @param array<mixed> $card `creditCardToken`, or `creditCard` and `creditCardHolderInfo`,
     *                           plus the `remoteIp` of the buyer
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see fields available in https://docs.asaas.com/reference/atualizar-cartao-de-credito-assinatura
     */
    public function updateCreditCard(string $id, array $card): array
    {
        SubscriptionCreditCardAsaasRequest::validate($card);

        return $this->put("subscriptions/{$id}/creditCard", $card);
    }

    /**
     * charges already generated by a subscription — the future ones do not
     * exist yet.
     *
     * @param string $id
     * @param array<mixed> $filters e.g. ['status' => 'PENDING']
     * @return array<mixed>
     * @throws ApiException
     */
    public function getPayments(string $id, array $filters = []): array
    {
        return $this->get("subscriptions/{$id}/payments", $filters);
    }

    /**
     * payment book (carnê) of a subscription: the raw PDF.
     *
     * @param string $id
     * @param int|null $month last month the book covers
     * @param int|null $year last year the book covers
     * @return string the PDF bytes, ready to save or stream
     * @throws ApiException
     */
    public function paymentBook(string $id, ?int $month = null, ?int $year = null): string
    {
        return $this->download("subscriptions/{$id}/paymentBook", array_filter([
            'month' => $month,
            'year'  => $year,
        ], static fn (?int $value): bool => $value !== null));
    }

    /**
     * configure the invoice (nota fiscal) issued for each charge.
     *
     * @param string $id
     * @param array<mixed> $settings `taxes` is required
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see fields available in https://docs.asaas.com/reference/criar-configuracao-para-emissao-de-notas-fiscais
     */
    public function createInvoiceSettings(string $id, array $settings): array
    {
        SubscriptionInvoiceSettingsAsaasRequest::validate($settings);

        return $this->post("subscriptions/{$id}/invoiceSettings", $settings);
    }

    /**
     * find the invoice settings
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function getInvoiceSettings(string $id): array
    {
        return $this->get("subscriptions/{$id}/invoiceSettings");
    }

    /**
     * update the invoice settings
     *
     * @param string $id
     * @param array<mixed> $settings `taxes` is required here too
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function updateInvoiceSettings(string $id, array $settings): array
    {
        SubscriptionInvoiceSettingsAsaasRequest::validate($settings);

        return $this->put("subscriptions/{$id}/invoiceSettings", $settings);
    }

    /**
     * remove the invoice settings — no more invoices are issued
     *
     * @param string $id
     * @return bool
     * @throws ApiException
     */
    public function destroyInvoiceSettings(string $id): bool
    {
        return $this->delete("subscriptions/{$id}/invoiceSettings");
    }

    /**
     * invoices issued for the charges of a subscription
     *
     * @param string $id
     * @param array<mixed> $filters
     * @return array<mixed>
     * @throws ApiException
     * @see params in https://docs.asaas.com/reference/listar-notas-fiscais-das-cobrancas-de-uma-assinatura
     */
    public function getInvoices(string $id, array $filters = []): array
    {
        return $this->get("subscriptions/{$id}/invoices", $filters);
    }
}
