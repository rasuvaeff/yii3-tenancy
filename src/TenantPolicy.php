<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

/**
 * What TenantResolutionMiddleware does when a request has no usable tenant
 * (unresolved/unknown key) or the resolved tenant is suspended.
 *
 * @api
 */
enum TenantPolicy: string
{
    case Reject = 'reject';
    case PassThrough = 'passthrough';
}
