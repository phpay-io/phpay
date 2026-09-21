<?php

namespace PHPay\Efi\Interface;

use PHPay\Contracts\{SupportsCharges, SupportsPixKeys, SupportsSubscriptions, SupportsWebhooks};
use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Efi\Resources\Pix\Pix;
use PHPay\Efi\Resources\PixCharge\PixCharge;
use PHPay\Efi\Resources\Subscription\Subscription;
use PHPay\Efi\Resources\Webhook\Webhook;

/**
 * the Efí gateway spans two APIs with the same credentials:
 *
 * - Cobranças: boletos — charge()
 * - Pix, over mTLS: Pix keys, webhooks, Pix Automático and Pix charges —
 *   pix(), webhook(), subscription() and pixCharge()
 *
 * customers are not declared: neither API keeps a customer record, so the
 * type system says so instead of a stub throwing at runtime.
 */
interface EfiGatewayInterface extends
    SupportsCharges,
    SupportsWebhooks,
    SupportsPixKeys,
    SupportsSubscriptions
{
    /**
     * get token of the Cobranças API
     *
     * @return array<string, mixed> token
     */
    public function getToken(): array;

    /**
     * get token of the Pix API
     *
     * @return array<string, mixed> token
     */
    public function getPixToken(): array;

    /**
     * create charge — a boleto, in the Cobranças API
     *
     * @param array<mixed> $charge
     * @return Charge charge
     */
    public function charge(array $charge = []): Charge;

    /**
     * Pix charges — immediate or with due date, in the Pix API
     *
     * @return PixCharge
     */
    public function pixCharge(): PixCharge;

    /**
     * webhooks of the Pix API
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook;

    /**
     * Pix keys
     *
     * @return Pix
     */
    public function pix(): Pix;

    /**
     * Pix Automático
     *
     * @return Subscription
     */
    public function subscription(): Subscription;
}
