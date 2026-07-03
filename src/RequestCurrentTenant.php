<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use LogicException;
use Rasuvaeff\Yii3Tenancy\Exception\TenantNotResolvedException;

/**
 * Set-once holder for the tenant of the current request. Writers (the
 * resolution middleware) depend on this concrete class; readers depend on
 * the CurrentTenant interface.
 *
 * @api
 */
final class RequestCurrentTenant implements CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        if ($this->tenant !== null) {
            throw new LogicException('Tenant is already set for the current request; use override() to replace it');
        }

        $this->tenant = $tenant;
    }

    /**
     * Explicit replacement for console/test contexts where one process
     * handles several tenants.
     */
    public function override(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    #[\Override]
    public function get(): Tenant
    {
        return $this->tenant ?? throw new TenantNotResolvedException('Tenant has not been resolved for the current request');
    }

    #[\Override]
    public function find(): ?Tenant
    {
        return $this->tenant;
    }

    #[\Override]
    public function isResolved(): bool
    {
        return $this->tenant !== null;
    }
}
