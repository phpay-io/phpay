<?php

namespace PHPay\Exceptions;

use BadMethodCallException;
use PHPay\Contracts\{Capability, GatewayInterface};

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

    /**
     * build a not implemented exception for a capability the gateway lacks,
     * listing what it does offer.
     *
     * @param GatewayInterface $gateway
     * @param Capability $capability
     * @return self
     */
    public static function forCapability(GatewayInterface $gateway, Capability $capability): self
    {
        $available = Capability::of($gateway);

        $offered = empty($available)
            ? 'nenhuma'
            : implode(', ', array_map(
                fn (Capability $item) => $item->label(),
                $available
            ));

        return new self(sprintf(
            '%s não suporta %s. Capacidades disponíveis: %s.',
            $gateway->name(),
            $capability->label(),
            $offered
        ));
    }
}
