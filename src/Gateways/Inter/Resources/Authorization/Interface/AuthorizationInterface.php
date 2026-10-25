<?php

namespace PHPay\Inter\Resources\Authorization\Interface;

use PHPay\Exceptions\ApiException;

interface AuthorizationInterface
{
    /**
     * exchange credentials for an access token.
     *
     * @return array<string, mixed>
     * @throws ApiException
     */
    public function getToken(): array;
}
