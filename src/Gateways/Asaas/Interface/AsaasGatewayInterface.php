<?php

namespace PHPay\Asaas\Interface;

use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Pix\Pix;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Asaas\Resources\Webhook\Webhook;
use PHPay\Contracts\GatewayInterface;

interface AsaasGatewayInterface extends GatewayInterface
{
    /**
     * get resource customer from gateway.
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(array $customer = []): Customer;

    /**
     * get resource charge from gateway.
     *
     * @return Charge
     */
    public function charge(): Charge;

    /**
     * get resource subscription from gateway.
     *
     * @return Subscription
     */
    public function subscription(): Subscription;

    /**
     * get resource webhook from gateway.
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook;

    /**
     * get resource pix from gateway.
     *
     * @param array<mixed> $pix
     * @return Pix
     */
    public function pix(array $pix = []): Pix;
}
