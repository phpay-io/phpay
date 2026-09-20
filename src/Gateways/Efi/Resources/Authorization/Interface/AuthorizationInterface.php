<?php

namespace PHPay\Efi\Resources\Authorization\Interface;

interface AuthorizationInterface
{
    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed>
     */
    public function getToken(): array;
}
