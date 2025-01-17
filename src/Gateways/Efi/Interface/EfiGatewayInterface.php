<?php

namespace Efi\Interface;

use Efi\Resources\Charge\Charge;
use PHPay\Contracts\GatewayInterface;

interface EfiGatewayInterface extends GatewayInterface
{
    /**
     * get token
     *
     * @return array<mixed> token
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
     * get resource customer from gateway.
     *
     * @param array<mixed> $pix
     * @return object
     */
    public function pix(array $pix = []): object;
}
