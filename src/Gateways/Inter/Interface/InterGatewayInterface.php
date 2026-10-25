<?php

namespace PHPay\Inter\Interface;

use PHPay\Contracts\{SupportsCharges, SupportsWebhookEvents, SupportsWebhooks};
use PHPay\Inter\Resources\Charge\Charge;
use PHPay\Inter\Resources\Webhook\Webhook;

/**
 * the Inter gateway speaks the Pix API, which is the BACEN specification.
 *
 * what it does **not** declare, and why:
 *
 * - **customers and subscriptions** do not exist in the API;
 * - **Pix keys** are not manageable: the API addresses a webhook by a key you
 *   already own, and offers no DICT endpoint to create, list or remove one;
 * - **refunds** exist as `PUT /pix/{e2eId}/devolucao/{id}`, but the BACEN
 *   schema requires the amount while `SupportsRefunds` promises that an
 *   absent amount refunds everything, and the call is addressed by the
 *   payment's `endToEndId` rather than by the charge. The refund lives on the
 *   charge resource instead, exactly as it does on Efí;
 * - **webhook signature**: the notification is authenticated by mTLS, which
 *   the certificate already covers — there is no signature scheme to verify.
 *
 * the boleto (`/cobranca/v3`) is a second API with its own paths and scopes,
 * and this version does not speak it.
 */
interface InterGatewayInterface extends
    SupportsCharges,
    SupportsWebhooks,
    SupportsWebhookEvents
{
    /**
     * get the access token, authorizing on first use and again once it expires
     *
     * @return array<string, mixed> token
     */
    public function getToken(): array;

    /**
     * Pix charges — immediate or with due date
     *
     * @return Charge
     */
    public function charge(): Charge;

    /**
     * webhooks, one per Pix key
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook;
}
