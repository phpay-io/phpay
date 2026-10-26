<?php

namespace PHPay\Support;

use Closure;
use PHPay\Contracts\SupportsChargeIntents;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Delivery;
use Throwable;

/**
 * a list of gateways tried in order, when trying the next one is safe.
 *
 * **falling back is about money, not about resilience**, exactly like the
 * retry it sits above. If the charge request failed in a way that does not
 * prove it never arrived, handing the same charge to another gateway creates
 * a SECOND charge — and here it is worse than a repeated retry, because a
 * retry can be deduplicated by an idempotency key and two different PSPs
 * share nothing that could deduplicate them. Nobody does it for you.
 *
 * So the question is never "did it fail?". It is "did it provably not
 * happen?", and {@see Delivery} answers it — the same answer `RetryPolicy`
 * uses, never a second copy.
 *
 * ```php
 * $gateway = new FallbackGateway([
 *     Gateway::ASAAS->make(['token' => $token]),
 *     Gateway::WOOVI->make(['appId' => $appId]),
 * ]);
 *
 * PHPay::gateway($gateway)->createCharge($intent);
 * ```
 */
final class FallbackGateway implements SupportsChargeIntents
{
    /** @var array<int, SupportsChargeIntents> */
    private array $gateways;

    /**
     * @param array<mixed> $gateways tried in order; the first is the primary
     * @param Closure|null $onAttempt `fn (string $gateway, ?Throwable $failure)`, after each try
     * @throws ValidationException
     */
    public function __construct(array $gateways, private ?Closure $onAttempt = null)
    {
        if ($gateways === []) {
            throw ValidationException::make('Fallback', self::messages()->empty);
        }

        foreach ($gateways as $gateway) {
            if (!$gateway instanceof SupportsChargeIntents) {
                throw ValidationException::make('Fallback', self::messages()->contract);
            }
        }

        /** @var array<int, SupportsChargeIntents> $lista */
        $lista = array_values($gateways);

        $this->gateways = $lista;
    }

    /**
     * the chain, as it reads in an exception message.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Fallback (' . implode(' → ', array_map(
            static fn (SupportsChargeIntents $gateway): string => $gateway->name(),
            $this->gateways
        )) . ')';
    }

    /**
     * every method ANY gateway in the chain can do.
     *
     * the union, not the intersection — and that is not the same mistake as
     * declaring the union of *capabilities* would be. A capability is checked
     * once, against this object; a method is checked per charge, and
     * createCharge() picks the gateways that do that one before trying any.
     * So a chain of Asaas and Rede really does both Pix and credit: just
     * never on the same gateway.
     *
     * @return array<int, Method>
     */
    public function supportedMethods(): array
    {
        $metodos = [];

        foreach ($this->gateways as $gateway) {
            foreach ($gateway->supportedMethods() as $metodo) {
                $metodos[$metodo->value] = $metodo;
            }
        }

        return array_values($metodos);
    }

    /**
     * the PRIMARY gateway's charge resource.
     *
     * there is no fallback on it, and there cannot be: it hands back a
     * stateful resource, and which gateway answers is only decided when the
     * terminal call happens, inside an object this one no longer holds. Use
     * createCharge() for the fallback; this is here because
     * SupportsChargeIntents extends SupportsCharges, and refusing it would be
     * declaring a capability and throwing from inside it.
     *
     * @return object
     */
    public function charge(): object
    {
        return $this->gateways[0]->charge();
    }

    /**
     * create the charge on the first gateway that can, and may.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createCharge(ChargeIntent $intent): array
    {
        $candidatos = array_values(array_filter(
            $this->gateways,
            static fn (SupportsChargeIntents $gateway): bool => in_array($intent->method, $gateway->supportedMethods(), true)
        ));

        if ($candidatos === []) {
            throw ValidationException::make('Fallback', sprintf(
                self::messages()->noCandidate,
                $intent->method->label(),
                $this->chain()
            ));
        }

        $ultima = null;

        foreach ($candidatos as $gateway) {
            try {
                $cobranca = $gateway->createCharge($intent);

                $this->report($gateway->name(), null);

                return $cobranca;
            } catch (ValidationException $failure) {
                /*
                 * a refusal of this gateway's own rules, raised before any
                 * HTTP — the Asaas wants a due date, the Woovi refuses one,
                 * the Cielo wants a brand. Nothing was sent, so the next one
                 * is safe, and its rules are different by definition.
                 */
                $this->report($gateway->name(), $failure);

                $ultima = $failure;
            } catch (ApiException $failure) {
                $this->report($gateway->name(), $failure);

                if (!self::mayFallBack($failure)) {
                    throw $failure;
                }

                $ultima = $failure;
            }
        }

        /* every candidate refused, and each one allowed the next to be tried */
        throw $ultima;
    }

    /**
     * whether this failure allows handing the charge to another gateway.
     *
     * | situação | cai? | |
     * | --- | :-: | --- |
     * | DNS, conexão, TLS | sim | provadamente não saiu |
     * | 401 / 403 | sim | recusou antes de processar |
     * | 429 | sim | recusou antes de processar |
     * | 4xx de payload | não | erraria igual no próximo |
     * | 5xx já na linha | não | pode ter criado a cobrança |
     * | timeout com conexão aberta | não | idem |
     *
     * @param ApiException $failure
     * @return bool
     */
    private static function mayFallBack(ApiException $failure): bool
    {
        $status = $failure->getStatusCode();

        /* a credential or a rate limit is refused before anything is done */
        if (in_array($status, [401, 403, 429], true)) {
            return true;
        }

        /* the gateway answered at all: it had the request, so it may have acted */
        if ($status !== 0) {
            return false;
        }

        $transporte = $failure->getPrevious();

        return $transporte !== null && Delivery::neverSent($transporte);
    }

    /**
     * @param string $gateway
     * @param Throwable|null $failure
     * @return void
     */
    private function report(string $gateway, ?Throwable $failure): void
    {
        if ($this->onAttempt !== null) {
            ($this->onAttempt)($gateway, $failure);
        }
    }

    /**
     * what each gateway in the chain does, for the message.
     *
     * @return string
     */
    private function chain(): string
    {
        return implode('; ', array_map(
            static fn (SupportsChargeIntents $gateway): string => $gateway->name() . ': ' . Method::labels($gateway->supportedMethods()),
            $this->gateways
        ));
    }

    /**
     * messages for every refusal above.
     *
     * @return object{empty: string, contract: string, noCandidate: string}
     */
    public static function messages(): object
    {
        return (object) [
            'empty'       => 'A lista de gateways do fallback não pode estar vazia.',
            'contract'    => 'Todo gateway da lista precisa implementar SupportsChargeIntents. Sem ele não dá para perguntar quais meios o gateway faz, e uma lista que não sabe isso entrega uma cobrança Pix a quem só faz cartão.',
            'noCandidate' => 'Nenhum gateway desta lista cria cobrança por %s. O que cada um faz: %s.',
        ];
    }
}
