<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

/**
 * @api
 */
enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
