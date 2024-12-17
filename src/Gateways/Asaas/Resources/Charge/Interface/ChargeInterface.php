<?php

namespace Asaas\Resources\Charge\Interface;

interface ChargeInterface
{
    public function setCustomer(string $customer): ChargeInterface;
    public function create(): array;
    public function createLean(): array;
}
