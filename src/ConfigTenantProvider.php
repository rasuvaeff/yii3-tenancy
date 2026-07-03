<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use InvalidArgumentException;

/**
 * Static tenant registry from configuration — for a handful of known
 * tenants (staging, small installations).
 *
 * @api
 */
final readonly class ConfigTenantProvider implements TenantProvider
{
    /**
     * @var array<string, Tenant>
     */
    private array $tenants;

    /**
     * @param array<string, array{name?: string, status?: string, attributes?: array<string, mixed>}> $definitions
     */
    public function __construct(array $definitions)
    {
        $tenants = [];
        foreach ($definitions as $id => $definition) {
            $status = $definition['status'] ?? TenantStatus::Active->value;

            $tenants[$id] = new Tenant(
                id: $id,
                name: $definition['name'] ?? '',
                status: TenantStatus::tryFrom($status)
                    ?? throw new InvalidArgumentException(sprintf('Unknown tenant status "%s" for tenant "%s"', $status, $id)),
                attributes: $definition['attributes'] ?? [],
            );
        }

        $this->tenants = $tenants;
    }

    #[\Override]
    public function find(string $key): ?Tenant
    {
        return $this->tenants[$key] ?? null;
    }
}
