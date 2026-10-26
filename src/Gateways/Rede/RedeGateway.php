<?php

namespace PHPay\Rede;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\{ClientFactory, Idempotency, MasksCredentials};
use PHPay\Rede\Enums\TransactionKindEnum;
use PHPay\Rede\Interface\RedeGatewayInterface;
use PHPay\Rede\Requests\RedeTransactionRequest;
use PHPay\Rede\Resources\Authorization\Authorization;
use PHPay\Rede\Resources\Charge\Charge;
use PHPay\Support\{Card, ChargeIntent, Method, Money};

class RedeGateway implements RedeGatewayInterface
{
    /**
     * keeps the credentials out of dumps
     */
    use MasksCredentials;

    /**
     * authorization shared by every resource, so the token is negotiated once
     */
    private ?Authorization $authorization = null;

    /**
     * construct
     *
     * no network call happens here — the token is negotiated on first use.
     *
     * @param string $affiliation PV
     * @param string $secret token
     * @param bool $sandbox
     * @param Client|null $client injected API client, mainly for tests
     * @param Client|null $oauthClient injected OAuth client, mainly for tests
     */
    public function __construct(
        private string $affiliation,
        #[\SensitiveParameter]
        private string $secret,
        private bool $sandbox = true,
        private ?Client $client = null,
        private ?Client $oauthClient = null,
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'Rede';
    }

    /**
     * the OAuth authorization, built once and shared by every resource.
     *
     * @return Authorization
     */
    public function authorization(): Authorization
    {
        if ($this->authorization === null) {
            $this->authorization = new Authorization(
                $this->affiliation,
                $this->secret,
                RedeEnvironment::tokenPath($this->sandbox),
                $this->oauthClient ?? ClientFactory::make([
                    'base_uri' => RedeEnvironment::oauth($this->sandbox),
                    'headers'  => ['accept' => 'application/json', 'user-agent' => 'PHPay'],
                ])
            );
        }

        return $this->authorization;
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->authorization(), $this->sandbox, $this->client);
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
     * the Rede is a credit acquirer and nothing else in this library: its
     * TransactionKindEnum has one case. This is the gateway that makes
     * supportedMethods() necessary — it declares SupportsCharges like the
     * other nine, and a Pix charge handed to it would never work.
     *
     * @return array<int, Method>
     */
    public function supportedMethods(): array
    {
        return [Method::CREDIT_CARD];
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
        $intent->ensureSupportedBy('Rede', $this->supportedMethods());

        if (!$intent->card instanceof Card) {
            throw ValidationException::make('Rede', RedeTransactionRequest::messages()->card);
        }

        return $this->charge()
            ->setTransaction($intent->extra())
            ->setReference($intent->reference ?? Idempotency::key())
            ->setCard(
                $intent->card->number,
                $intent->card->holderName,
                $intent->card->expirationMonth,
                $intent->card->expirationYear,
                $intent->card->securityCode
            )
            ->setPayment($intent->amount, TransactionKindEnum::CREDIT, $intent->installments)
            ->create();
    }

    public function refund(string $id, Money|int|null $amount = null): array
    {
        return $this->charge()->refund($id, $amount);
    }
}
