<?php

namespace PHPay\AbacatePay\Resources\Coupon\Interface;

interface CouponInterface
{
    /**
     * create coupon
     *
     * @param array<mixed> $coupon
     * @return array<mixed>
     */
    public function create(array $coupon): array;

    /**
     * list coupons
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return CouponInterface
     */
    public function setQueryParams(array $queryParams): CouponInterface;
}
