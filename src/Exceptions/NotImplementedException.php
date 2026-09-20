<?php

namespace PHPay\Exceptions;

use BadMethodCallException;

/**
 * thrown when a gateway does not implement a resource declared by GatewayInterface.
 */
class NotImplementedException extends BadMethodCallException implements PHPayException
{
    /**
     * build a not implemented exception for a given gateway resource.
     *
     * @param string $gateway
     * @param string $resource
     * @return self
     */
    public static function make(string $gateway, string $resource): self
    {
        return new self("{$gateway}: o recurso '{$resource}' ainda não foi implementado neste gateway.");
    }
}
