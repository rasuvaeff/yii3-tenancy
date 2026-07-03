<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves the tenant for the incoming request and publishes it via
 * RequestCurrentTenant and the Tenant::class request attribute. Place it
 * BEFORE authentication middleware — the tenant usually determines the
 * user store.
 *
 * @api
 */
final readonly class TenantResolutionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantProvider $provider,
        private RequestCurrentTenant $currentTenant,
        private ResponseFactoryInterface $responseFactory,
        private TenantPolicy $unresolvedPolicy = TenantPolicy::Reject,
        private TenantPolicy $suspendedPolicy = TenantPolicy::Reject,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolver->resolve($request);
        $tenant = $key === null ? null : $this->provider->find($key);

        if ($tenant === null) {
            return $this->unresolvedPolicy === TenantPolicy::Reject
                ? $this->responseFactory->createResponse(404)
                : $handler->handle($request);
        }

        if ($tenant->status === TenantStatus::Suspended && $this->suspendedPolicy === TenantPolicy::Reject) {
            return $this->responseFactory->createResponse(403);
        }

        $this->currentTenant->set($tenant);

        return $handler->handle($request->withAttribute(Tenant::class, $tenant));
    }
}
