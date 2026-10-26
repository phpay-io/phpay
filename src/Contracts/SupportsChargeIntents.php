<?php

namespace PHPay\Contracts;

use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{ChargeIntent, Method};

/**
 * creating a charge from a portable description, instead of that gateway's
 * own payload.
 *
 * it declares an operation, not a resource getter, for the same reason
 * `SupportsRefunds` does: what diverges between the gateways is not how to
 * reach the charge, it is the call. `setCharge()` keeps taking the raw
 * payload of that gateway and nothing here replaces it.
 *
 * `supportedMethods()` exists because `SupportsCharges` answers "does this
 * gateway charge?" and never "does it charge *that way*?". All ten declare
 * `SupportsCharges`, and even so the Rede only does credit and only four do
 * billets. Without the question being asked, falling back from a Pix charge
 * to a gateway that cannot do Pix fails after the one that could was already
 * given up on.
 */
interface SupportsChargeIntents extends SupportsCharges
{
    /**
     * the methods `createCharge()` accepts on this gateway.
     *
     * it describes the portable path, not everything the gateway sells: a
     * method the gateway offers but this path cannot build yet does not
     * belong here, because declaring it would promise a call that throws.
     *
     * @return array<int, Method>
     */
    public function supportedMethods(): array;

    /**
     * create a charge from a portable description.
     *
     * @param ChargeIntent $intent
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createCharge(ChargeIntent $intent): array;
}
