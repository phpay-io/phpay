<?php

namespace PHPay\Efi\Interface;

use PHPay\Contracts\GatewayInterface;
use PHPay\Efi\Resources\Charge\Charge;

interface EfiGatewayInterface extends GatewayInterface
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
     * @param array<string> $charge
     * @return Charge charge
     */
    public function charge(array $charge = []): Charge;

    /**
     * get resource pix from gateway.
     *
     * @return object
     */
    public function pix(): object;
}
