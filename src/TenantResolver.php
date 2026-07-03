<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Extracts a candidate tenant key from the request. Implementations must
 * validate the candidate with Tenant::isValidId() and return null on
 * mismatch — keys taken from requests are untrusted input.
 *
 * @api
 */
interface TenantResolver
{
    public function resolve(ServerRequestInterface $request): ?string;
}
