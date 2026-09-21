<?php

namespace PHPay\Rede\Interface;

use PHPay\Contracts\SupportsCharges;
use PHPay\Rede\Resources\Authorization\Authorization;
use PHPay\Rede\Resources\Charge\Charge;

/**
 * the Rede gateway offers charges only.
 *
 * it is an acquirer, and the narrowest shape in the library alongside Efí.
 * There is no customer resource, no webhook CRUD and no Pix key management.
 * The transaction payload does carry a `subscription` flag, but that marks a
 * recurring charge for the acquirer — it is not a subscription you can list,
 * change or cancel, so SupportsSubscriptions is deliberately not declared.
 */
interface RedeGatewayInterface extends SupportsCharges
{
    /**
     * get resource charge from gateway.
     *
     * @return Charge
     */
    public function charge(): Charge;

    /**
     * the OAuth authorization, exposed so a long-running process can inspect
     * or reset the token it holds.
     *
     * @return Authorization
     */
    public function authorization(): Authorization;
}
