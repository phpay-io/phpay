<?php

namespace PHPay\Contracts;

/**
 * the gateway manages Pix keys and static QR Codes as a resource.
 *
 * this is narrower than "accepts Pix": most gateways treat Pix as a billing
 * type of a charge and never expose key management, which only a PSP that
 * issues its own keys can offer.
 */
interface SupportsPixKeys extends GatewayInterface
{
    /**
     * get resource pix from gateway.
     *
     * @return object
     */
    public function pix(): object;
}
