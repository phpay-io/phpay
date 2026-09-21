<?php

namespace PHPay\AbacatePay\Interface;

use PHPay\AbacatePay\Resources\Charge\Charge;
use PHPay\AbacatePay\Resources\Coupon\Coupon;
use PHPay\AbacatePay\Resources\Customer\Customer;
use PHPay\Contracts\{SupportsCharges, SupportsCustomers};
use PHPay\Support\Customer as CustomerData;

/**
 * the AbacatePay gateway offers customers and charges.
 *
 * it is Pix native, and still does not declare SupportsPixKeys: that
 * capability is about managing keys and static QR Codes, which only a PSP
 * that issues its own keys offers. Here Pix is the payment method of a
 * billing — the only one accepted.
 *
 * SupportsSubscriptions is not declared either: the API documents ONE_TIME as
 * the only accepted frequency.
 */
interface AbacatePayGatewayInterface extends
    SupportsCustomers,
    SupportsCharges
{
    /**
     * get resource customer from gateway.
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(CustomerData|array $customer = []): Customer;

    /**
     * get resource charge from gateway.
     *
     * @return Charge
     */
    public function charge(): Charge;

    /**
     * discount coupons.
     *
     * gateway specific: no other gateway in the library has them, so this is
     * not a capability and is reachable only from the concrete gateway.
     *
     * @return Coupon
     */
    public function coupons(): Coupon;
}
