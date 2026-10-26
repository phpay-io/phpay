<?php

namespace PHPay;

use PHPay\Contracts\{Capability, GatewayInterface, SupportsChargeIntents, SupportsCharges, SupportsCustomers, SupportsPixKeys, SupportsRefunds, SupportsSubscriptions, SupportsWebhookEvents, SupportsWebhookSignature, SupportsWebhooks};
use PHPay\Exceptions\{ApiException, NotImplementedException, ValidationException};
use PHPay\Support\{ChargeIntent, Customer as CustomerData, Gateway, Method, Money};
use PHPay\Webhooks\{Event, VerifiesSignature};

/**
 * entry point of the library.
 *
 * it accepts any gateway and dispatches to it. because the gateway is only
 * known at runtime, each resource is guarded by the capability the gateway
 * declares — ask with supports() to branch, or let the call throw a message
 * naming what the gateway does offer.
 *
 * holding the concrete gateway instead gives the same guarantee at analysis
 * time: calling pix() on a gateway that does not implement SupportsPixKeys is
 * a static error, not a runtime one.
 */
class PHPay implements GatewayInterface
{
    /**
     * instance of PHPay.
     *
     * @param GatewayInterface $gateway
     */
    public function __construct(
        protected GatewayInterface $gateway
    ) {
    }

    /**
     * instance of PHPay.
     *
     * @param GatewayInterface $gateway
     * @return PHPay
     */
    public static function gateway(GatewayInterface $gateway): PHPay
    {
        return new PHPay($gateway);
    }

    /**
     * build a gateway by name and wrap it.
     *
     * for whoever does not know the gateway while writing the code — a name
     * from configuration, a choice made at runtime, a framework bridge turning
     * an array into an object:
     *
     * ```php
     * $phpay = PHPay::make('asaas', ['token' => $token, 'sandbox' => true]);
     * ```
     *
     * `gateway()` above is still the way in when the gateway *is* known then,
     * and it is the one that gives static checking — holding the concrete
     * gateway makes a missing capability an analysis error instead of a
     * runtime one.
     *
     * @param string|Gateway $gateway the name, or the case
     * @param array<string, mixed> $config credentials and options of that gateway
     * @return PHPay
     * @throws ValidationException when the name or the config is wrong
     */
    public static function make(string|Gateway $gateway, array $config = []): PHPay
    {
        $gateway = $gateway instanceof Gateway ? $gateway : Gateway::named($gateway);

        return new PHPay($gateway->make($config));
    }

    /**
     * name of the wrapped gateway.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->gateway->name();
    }

    /**
     * whether the wrapped gateway offers the given capability.
     *
     * @param Capability $capability
     * @return bool
     */
    public function supports(Capability $capability): bool
    {
        return $capability->supportedBy($this->gateway);
    }

    /**
     * every capability the wrapped gateway offers.
     *
     * @return array<int, Capability>
     */
    public function capabilities(): array
    {
        return Capability::of($this->gateway);
    }

    /**
     * get resource customer from gateway.
     *
     * @param array<mixed> $customer
     * @return object
     * @throws NotImplementedException
     */
    public function customer(CustomerData|array $customer = []): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsCustomers) {
            throw NotImplementedException::forCapability($gateway, Capability::CUSTOMERS);
        }

        return $gateway->customer($customer);
    }

    /**
     * get resource charge from gateway.
     *
     * @return object
     * @throws NotImplementedException
     */
    public function charge(): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsCharges) {
            throw NotImplementedException::forCapability($gateway, Capability::CHARGES);
        }

        return $gateway->charge();
    }

    /**
     * give a received payment back, fully or in part.
     *
     * the only capability that performs the operation here instead of handing
     * back a resource — see SupportsRefunds for why.
     *
     * @param string $id the identifier that gateway uses for the paid transaction
     * @param Money|int|null $amount null refunds the full value
     * @return array<mixed>
     * @throws NotImplementedException
     * @throws ApiException
     */
    public function refund(string $id, Money|int|null $amount = null): array
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsRefunds) {
            throw NotImplementedException::forCapability($gateway, Capability::REFUNDS);
        }

        return $gateway->refund($id, $amount);
    }

    /**
     * create a charge from a portable description.
     *
     * the same ChargeIntent reaches every gateway that declares the
     * capability; each one translates it into its own payload. use
     * supportedMethods() to ask whether this gateway does that method before
     * building the intent, or let the call refuse with a message naming what
     * it does accept.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws NotImplementedException|ValidationException|ApiException
     */
    public function createCharge(ChargeIntent $intent): array
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsChargeIntents) {
            throw NotImplementedException::forCapability($gateway, Capability::CHARGE_INTENTS);
        }

        return $gateway->createCharge($intent);
    }

    /**
     * the payment methods createCharge() accepts on this gateway.
     *
     * @return array<int, Method>
     * @throws NotImplementedException
     */
    public function supportedMethods(): array
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsChargeIntents) {
            throw NotImplementedException::forCapability($gateway, Capability::CHARGE_INTENTS);
        }

        return $gateway->supportedMethods();
    }

    /**
     * get resource webhook from gateway.
     *
     * @param array<mixed> $webhook
     * @return object
     * @throws NotImplementedException
     */
    public function webhook(array $webhook = []): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsWebhooks) {
            throw NotImplementedException::forCapability($gateway, Capability::WEBHOOKS);
        }

        return $gateway->webhook($webhook);
    }

    /**
     * get resource pix from gateway.
     *
     * @return object
     * @throws NotImplementedException
     */
    public function pix(): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsPixKeys) {
            throw NotImplementedException::forCapability($gateway, Capability::PIX_KEYS);
        }

        return $gateway->pix();
    }

    /**
     * get resource subscription from gateway.
     *
     * @return object
     * @throws NotImplementedException
     */
    public function subscription(): object
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsSubscriptions) {
            throw NotImplementedException::forCapability($gateway, Capability::SUBSCRIPTIONS);
        }

        return $gateway->subscription();
    }

    /**
     * read a webhook notification into events.
     *
     * returns a list, never a single event — see SupportsWebhookEvents for
     * why. Verify the signature first, with the raw body.
     *
     * @param string|array<mixed> $payload the raw body, or it already decoded
     * @return array<int, Event>
     * @throws NotImplementedException
     * @throws ValidationException
     */
    public function webhookEvents(string|array $payload): array
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsWebhookEvents) {
            throw NotImplementedException::forCapability($gateway, Capability::WEBHOOK_EVENTS);
        }

        return $gateway->webhookEvents($payload);
    }

    /**
     * get resource webhook signature from gateway.
     *
     * typed as the contract and not as `object`, because this is the path
     * generic code takes: whoever resolved the gateway by name reaches the
     * verifier through here.
     *
     * @return VerifiesSignature
     * @throws NotImplementedException
     */
    public function webhookSignature(): VerifiesSignature
    {
        $gateway = $this->gateway;

        if (!$gateway instanceof SupportsWebhookSignature) {
            throw NotImplementedException::forCapability($gateway, Capability::WEBHOOK_SIGNATURE);
        }

        $verifier = $gateway->webhookSignature();

        /*
         * a capacidade promete `object`, para não quebrar gateway próprio que
         * a implemente — então o contrato é conferido aqui. Nos dez da
         * biblioteca nunca dispara, e há teste varrendo o enum para garantir.
         */
        if (!$verifier instanceof VerifiesSignature) {
            throw ValidationException::make($gateway->name(), sprintf(
                'O verificador de assinatura deste gateway não cumpre %s. '
                . 'Gateway próprio que declare a capacidade precisa devolver um verificador que cumpra o contrato.',
                VerifiesSignature::class
            ));
        }

        return $verifier;
    }
}
