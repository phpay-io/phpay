<?php

namespace PHPay\Contracts;

use PHPay\Exceptions\ApiException;
use PHPay\Support\Money;

/**
 * the gateway can give a received payment back.
 *
 * unlike the other capabilities, this one declares an operation instead of a
 * resource getter. What diverged between the gateways was never how to reach
 * the charge — it was the call itself: three names for the same thing
 * (`refund`, `cancel`, `void`), three units, and three meanings for a missing
 * amount. Handing back a resource would leave the caller guessing the method
 * name per gateway, which is the divergence this capability exists to close.
 *
 * declared only where the gateway documents a refund of a *received* payment.
 * Cancelling a charge nobody paid is not a refund, and a gateway that only
 * offers that does not declare this.
 */
interface SupportsRefunds extends SupportsCharges
{
    /**
     * give a received payment back, fully or in part.
     *
     * @param string $id the identifier that gateway uses for the paid
     *                   transaction — the charge, the sale or the payment
     * @param Money|int|null $amount null refunds the full value; a bare int is
     *                               read in the unit that gateway has always
     *                               expected
     * @return array<mixed>
     * @throws ApiException
     */
    public function refund(string $id, Money|int|null $amount = null): array;
}
