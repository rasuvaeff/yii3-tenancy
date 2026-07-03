<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
final readonly class HeaderTenantResolver implements TenantResolver
{
    public function __construct(
        private string $headerName = 'X-Tenant-Id',
    ) {
        if ($this->headerName === '') {
            throw new InvalidArgumentException('Header name must not be empty');
        }
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        $candidate = trim($request->getHeaderLine($this->headerName));

        return Tenant::isValidId($candidate) ? $candidate : null;
    }
}
