<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3Tenancy\Tenant;
use Rasuvaeff\Yii3Tenancy\TenantStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(Tenant::class)]
final class TenantTest
{
    public function constructsWithDefaults(): void
    {
        $tenant = new Tenant(id: 'acme');

        Assert::same($tenant->id, 'acme');
        Assert::same($tenant->name, '');
        Assert::same($tenant->status, TenantStatus::Active);
        Assert::same($tenant->attributes, []);
        Assert::true($tenant->isActive());
    }

    public function suspendedTenantIsNotActive(): void
    {
        $tenant = new Tenant(id: 'acme', status: TenantStatus::Suspended);

        Assert::false($tenant->isActive());
    }

    #[DataProvider('validIdProvider')]
    public function acceptsValidId(string $id): void
    {
        Assert::same((new Tenant(id: $id))->id, $id);
        Assert::true(Tenant::isValidId($id));
    }

    public static function validIdProvider(): iterable
    {
        yield 'plain' => ['acme'];
        yield 'digits' => ['42'];
        yield 'mixed case' => ['AcmeInc'];
        yield 'hyphen and underscore' => ['acme-inc_2'];
        yield 'max length 64' => [str_repeat('a', 64)];
    }

    #[DataProvider('invalidIdProvider')]
    public function rejectsInvalidId(string $id): void
    {
        Assert::false(Tenant::isValidId($id));
        Expect::exception(InvalidArgumentException::class);

        new Tenant(id: $id);
    }

    public static function invalidIdProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'leading hyphen' => ['-acme'];
        yield 'dot' => ['acme.inc'];
        yield 'space' => ['acme inc'];
        yield 'colon' => ['acme:1'];
        yield 'too long 65' => [str_repeat('a', 65)];
        yield 'cyrillic' => ['клиент'];
    }
}
