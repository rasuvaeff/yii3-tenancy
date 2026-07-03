<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Tenancy\ConfigTenantProvider;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\RequestCurrentTenant;
use Rasuvaeff\Yii3Tenancy\Tenant;
use Rasuvaeff\Yii3Tenancy\TenantPolicy;
use Rasuvaeff\Yii3Tenancy\TenantResolutionMiddleware;
use Rasuvaeff\Yii3Tenancy\Tests\Support\FakeHandler;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(TenantResolutionMiddleware::class)]
final class TenantResolutionMiddlewareTest
{
    private RequestCurrentTenant $currentTenant;

    private FakeHandler $handler;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->currentTenant = new RequestCurrentTenant();
        $this->handler = new FakeHandler();
    }

    public function resolvedTenantIsPublished(): void
    {
        $middleware = $this->middleware(tenants: ['acme' => []]);
        $request = new ServerRequest('GET', '/', ['X-Tenant-Id' => 'acme']);

        $response = $middleware->process($request, $this->handler);

        Assert::same($response->getStatusCode(), 200);
        Assert::same($this->currentTenant->get()->id, 'acme');
        Assert::instanceOf($this->handler->handledRequest?->getAttribute(Tenant::class), Tenant::class);
    }

    public function unresolvedRequestIsRejectedWith404(): void
    {
        $middleware = $this->middleware(tenants: ['acme' => []]);

        $response = $middleware->process(new ServerRequest('GET', '/'), $this->handler);

        Assert::same($response->getStatusCode(), 404);
        Assert::null($this->handler->handledRequest);
        Assert::false($this->currentTenant->isResolved());
    }

    public function unknownKeyIsTreatedAsUnresolved(): void
    {
        $middleware = $this->middleware(tenants: ['acme' => []]);
        $request = new ServerRequest('GET', '/', ['X-Tenant-Id' => 'globex']);

        Assert::same($middleware->process($request, $this->handler)->getStatusCode(), 404);
    }

    public function unresolvedPassThroughInvokesHandlerWithoutTenant(): void
    {
        $middleware = $this->middleware(
            tenants: ['acme' => []],
            unresolvedPolicy: TenantPolicy::PassThrough,
        );

        $response = $middleware->process(new ServerRequest('GET', '/'), $this->handler);

        Assert::same($response->getStatusCode(), 200);
        Assert::false($this->currentTenant->isResolved());
    }

    public function suspendedTenantIsRejectedWith403(): void
    {
        $middleware = $this->middleware(tenants: ['acme' => ['status' => 'suspended']]);
        $request = new ServerRequest('GET', '/', ['X-Tenant-Id' => 'acme']);

        $response = $middleware->process($request, $this->handler);

        Assert::same($response->getStatusCode(), 403);
        Assert::false($this->currentTenant->isResolved());
    }

    public function suspendedPassThroughPublishesTenant(): void
    {
        $middleware = $this->middleware(
            tenants: ['acme' => ['status' => 'suspended']],
            suspendedPolicy: TenantPolicy::PassThrough,
        );
        $request = new ServerRequest('GET', '/', ['X-Tenant-Id' => 'acme']);

        $response = $middleware->process($request, $this->handler);

        Assert::same($response->getStatusCode(), 200);
        Assert::same($this->currentTenant->get()->id, 'acme');
    }

    /**
     * @param array<string, array{name?: string, status?: string, attributes?: array<string, mixed>}> $tenants
     */
    private function middleware(
        array $tenants,
        TenantPolicy $unresolvedPolicy = TenantPolicy::Reject,
        TenantPolicy $suspendedPolicy = TenantPolicy::Reject,
    ): TenantResolutionMiddleware {
        return new TenantResolutionMiddleware(
            resolver: new HeaderTenantResolver(),
            provider: new ConfigTenantProvider($tenants),
            currentTenant: $this->currentTenant,
            responseFactory: new Psr17Factory(),
            unresolvedPolicy: $unresolvedPolicy,
            suspendedPolicy: $suspendedPolicy,
        );
    }
}
