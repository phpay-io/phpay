<?php

namespace PHPay\Cielo;

use GuzzleHttp\Client;
use PHPay\Cielo\Interface\CieloGatewayInterface;
use PHPay\Cielo\Requests\{CieloSaleRequest, CieloWebhookEventRequest};
use PHPay\Cielo\Resources\Charge\Charge;
use PHPay\Cielo\Resources\Subscription\Subscription;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\MasksCredentials;
use PHPay\Support\{Card, ChargeIntent, Method, Money};
use PHPay\Webhooks\Event;

class CieloGateway implements CieloGatewayInterface
{
    /**
     * keeps the credentials out of dumps
     */
    use MasksCredentials;

    /**
     * construct
     *
     * @param string $merchantId
     * @param string $merchantKey
     * @param bool $sandbox
     * @param Client|null $client injected write client, mainly for tests
     * @param Client|null $queryClient injected query client, mainly for tests
     */
    public function __construct(
        private string $merchantId,
        #[\SensitiveParameter]
        private string $merchantKey,
        private bool $sandbox = true,
        private ?Client $client = null,
        private ?Client $queryClient = null,
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Cielo';
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge(
            $this->merchantId,
            $this->merchantKey,
            $this->sandbox,
            $this->client,
            $this->queryClient
        );
    }

    /**
     * refund a received payment, fully or in part.
     *
     * the capability's operation, delegated to the charge resource that owns
     * the endpoint.
     *
     * @param string $id
     * @param Money|int|null $amount null refunds the full value
     * @return array<mixed>
     * @throws ApiException
     */
    /**
     * the payment methods createCharge() accepts here.
     *
     * the Cielo is the widest of the ten on the portable path: it takes the
     * card number on the server, so the credit method is reachable. Debit is
     * absent because it needs 3DS authentication, which the library does not
     * model.
     *
     * @return array<int, Method>
     */
    public function supportedMethods(): array
    {
        return [Method::PIX, Method::BOLETO, Method::CREDIT_CARD];
    }

    /**
     * create a charge from a portable description.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createCharge(ChargeIntent $intent): array
    {
        $intent->ensureSupportedBy('Cielo', $this->supportedMethods());

        $charge = $this->charge()->setSale(CieloSaleRequest::fromIntent($intent));

        if ($intent->customer !== null) {
            $charge->setCustomer($intent->customer);
        }

        if ($intent->method === Method::PIX) {
            $charge->setPix($intent->amount);
        } elseif ($intent->method === Method::BOLETO) {
            $charge->setBoleto($intent->amount);
        } else {
            if (!$intent->card instanceof Card) {
                throw ValidationException::make('Cielo', CieloSaleRequest::messages()->payment);
            }

            $charge->setCreditCard(
                $intent->amount,
                CieloSaleRequest::cardFromIntent($intent->card),
                $intent->installments
            );
        }

        return $charge->create();
    }

    public function refund(string $id, Money|int|null $amount = null): array
    {
        return $this->charge()->refund($id, $amount);
    }

    /**
     * subscription
     *
     * @return Subscription
     */
    public function subscription(): Subscription
    {
        return new Subscription(
            $this->merchantId,
            $this->merchantKey,
            $this->sandbox,
            $this->client,
            $this->queryClient
        );
    }

    /**
     * read a webhook notification into events.
     *
     * returns a list, never a single event — see SupportsWebhookEvents.
     * Verify the signature first, with the raw body.
     *
     * @param string|array<mixed> $payload the raw body, or it already decoded
     * @return array<int, Event>
     * @throws ValidationException
     */
    public function webhookEvents(string|array $payload): array
    {
        return CieloWebhookEventRequest::parse($payload);
    }
}
