<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the tenant key from the first subdomain label under a configured
 * base domain: with baseDomain "example.com" the host "acme.example.com"
 * yields "acme". Nested subdomains ("a.b.example.com") and the bare base
 * domain yield null.
 *
 * @api
 */
final readonly class SubdomainTenantResolver implements TenantResolver
{
    private string $baseDomain;

    public function __construct(string $baseDomain)
    {
        $normalized = strtolower(ltrim(trim($baseDomain), '.'));

        if ($normalized === '') {
            throw new InvalidArgumentException('Base domain must not be empty');
        }

        $this->baseDomain = $normalized;
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        $host = strtolower($request->getUri()->getHost());
        $suffix = '.' . $this->baseDomain;

        if (!str_ends_with($host, $suffix)) {
            return null;
        }

        $candidate = substr($host, 0, -strlen($suffix));

        if ($candidate === '' || str_contains($candidate, '.')) {
            return null;
        }

        return Tenant::isValidId($candidate) ? $candidate : null;
    }
}
