<?php

namespace PHPay\Efi\Interface;

use PHPay\Contracts\SupportsCharges;
use PHPay\Efi\Resources\Charge\Charge;

/**
 * the Efí gateway currently offers charges only.
 *
 * customers, webhooks, Pix keys and subscriptions are not declared: the
 * gateway does not implement them, so the type system says so instead of a
 * stub throwing at runtime.
 */
interface EfiGatewayInterface extends SupportsCharges
{
    /**
     * get token
     *
     * @return array<string, mixed> token
     */
    public function getToken(): array;

    /**
     * create charge
     *
     * @param array<mixed> $charge
     * @return Charge charge
     */
    public function charge(array $charge = []): Charge;
}
