<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use InvalidArgumentException;

/**
 * @api
 */
final readonly class Tenant
{
    private const string ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/';

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $id,
        public string $name = '',
        public TenantStatus $status = TenantStatus::Active,
        public array $attributes = [],
    ) {
        if (!self::isValidId($id)) {
            throw new InvalidArgumentException(sprintf('Invalid tenant id "%s"', $id));
        }
    }

    public static function isValidId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
