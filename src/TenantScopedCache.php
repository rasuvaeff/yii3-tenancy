<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 decorator isolating cache entries per tenant by prefixing every
 * key with "t.{tenantId}." (dots, not colons — PSR-16 reserves ":").
 *
 * clear() delegates to the inner cache and wipes ALL tenants — PSR-16 has
 * no prefix-scoped clear. Do not call it in tenant-scoped code paths.
 *
 * @api
 */
final readonly class TenantScopedCache implements CacheInterface
{
    public function __construct(
        private CacheInterface $cache,
        private CurrentTenant $currentTenant,
    ) {}

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->prefixed($key), $default);
    }

    #[\Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->cache->set($this->prefixed($key), $value, $ttl);
    }

    #[\Override]
    public function delete(string $key): bool
    {
        return $this->cache->delete($this->prefixed($key));
    }

    #[\Override]
    public function clear(): bool
    {
        return $this->cache->clear();
    }

    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            /** @var mixed */
            $values[$key] = $this->cache->get($this->prefixed($key), $default);
        }

        return $values;
    }

    #[\Override]
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $result = true;
        /**
         * @var mixed $key
         * @var mixed $value
         */
        foreach ($values as $key => $value) {
            $result = $this->cache->set($this->prefixed((string) $key), $value, $ttl) && $result;
        }

        return $result;
    }

    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        $result = true;
        foreach ($keys as $key) {
            $result = $this->cache->delete($this->prefixed($key)) && $result;
        }

        return $result;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->cache->has($this->prefixed($key));
    }

    private function prefixed(string $key): string
    {
        return 't.' . $this->currentTenant->get()->id . '.' . $key;
    }
}
