<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the tenant key from the first path segment after a configured
 * prefix: with prefix "/t" the path "/t/acme/dashboard" yields "acme".
 *
 * @api
 */
final readonly class PathTenantResolver implements TenantResolver
{
    private string $prefix;

    public function __construct(string $prefix = '/t')
    {
        $normalized = rtrim($prefix, '/');

        if ($normalized === '' || !str_starts_with($normalized, '/')) {
            throw new InvalidArgumentException(sprintf('Path prefix must start with "/" and not be the root, got "%s"', $prefix));
        }

        $this->prefix = $normalized;
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        $path = $request->getUri()->getPath();

        if (!str_starts_with($path, $this->prefix . '/')) {
            return null;
        }

        $rest = substr($path, strlen($this->prefix) + 1);
        $slashPosition = strpos($rest, '/');
        $candidate = $slashPosition === false ? $rest : substr($rest, 0, $slashPosition);

        return Tenant::isValidId($candidate) ? $candidate : null;
    }
}
