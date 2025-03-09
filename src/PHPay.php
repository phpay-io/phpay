<?php

namespace PHPay;

use PHPay\Contracts\GatewayInterface;

class PHPay implements GatewayInterface
{
    /**
     * instance of PHPay.
     *
     * @param GatewayInterface $gateway
     */
    public function __construct(
        protected GatewayInterface $gateway
    ) {
        $this->gateway = $gateway;
    }

    /**
     * instance of PHPay.
     *
     * @param GatewayInterface $gateway
     * @return PHPay
     */
    public static function gateway(GatewayInterface $gateway): PHPay
    {
        return new PHPay($gateway);
    }

    /**
     * get resource customer from gateway.
     *
     * @param array<mixed> $customer
     * @return object
     */
    public function customer(array $customer = []): object
    {
        return $this->gateway->customer($customer);
    }

    /**
     * get resource charge from gateway.
     *
     * @return object
     */
    public function charge(): object
    {
        return $this->gateway->charge();
    }

    /**
     * get resource webhook from gateway.
     *
     * @param array<mixed> $webhook
     * @return object
     */
    public function webhook(array $webhook = []): object
    {
        return $this->gateway->webhook($webhook);
    }

    /**
     * get resource pix from gateway.
     *
     * @param array<mixed> $pix
     * @return object
     */
    public function pix(array $pix = []): object
    {
        return $this->gateway->pix($pix);
    }

    /**
     * get resource subscription from gateway.
     *
     * @return object
     */
    public function subscription(): object
    {
        return $this->gateway->subscription();
    }
}
