<?php

declare(strict_types=1);

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rasuvaeff\Yii3Tenancy\CompositeTenantResolver;
use Rasuvaeff\Yii3Tenancy\ConfigTenantProvider;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\RequestCurrentTenant;
use Rasuvaeff\Yii3Tenancy\SubdomainTenantResolver;
use Rasuvaeff\Yii3Tenancy\Tenant;
use Rasuvaeff\Yii3Tenancy\TenantResolutionMiddleware;

require dirname(__DIR__) . '/vendor/autoload.php';

final class EchoHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $handledRequest = null;

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handledRequest = $request;

        return new Response(200);
    }
}

$provider = new ConfigTenantProvider([
    'acme' => ['name' => 'Acme Inc', 'attributes' => ['plan' => 'pro']],
    'globex' => ['name' => 'Globex', 'status' => 'suspended'],
]);

$currentTenant = new RequestCurrentTenant();

$middleware = new TenantResolutionMiddleware(
    resolver: new CompositeTenantResolver(
        new HeaderTenantResolver(),
        new SubdomainTenantResolver(baseDomain: 'example.com'),
    ),
    provider: $provider,
    currentTenant: $currentTenant,
    responseFactory: new Psr17Factory(),
);

// 1. Resolved from header
$handler = new EchoHandler();
$response = $middleware->process(new ServerRequest('GET', '/', ['X-Tenant-Id' => 'acme']), $handler);
$tenant = $currentTenant->get();
echo "header  -> {$response->getStatusCode()} tenant={$tenant->id} plan={$tenant->attributes['plan']}\n";
echo 'request attribute -> ' . $handler->handledRequest?->getAttribute(Tenant::class)?->id . "\n";

// 2. Resolved from subdomain
$currentTenant = new RequestCurrentTenant();
$middleware2 = new TenantResolutionMiddleware(
    resolver: new SubdomainTenantResolver(baseDomain: 'example.com'),
    provider: $provider,
    currentTenant: $currentTenant,
    responseFactory: new Psr17Factory(),
);
$response = $middleware2->process(new ServerRequest('GET', 'https://acme.example.com/'), new EchoHandler());
echo "subdomain -> {$response->getStatusCode()} tenant={$currentTenant->get()->id}\n";

// 3. Unknown tenant -> 404, suspended tenant -> 403
$response = $middleware2->process(new ServerRequest('GET', 'https://nobody.example.com/'), new EchoHandler());
echo "unknown -> {$response->getStatusCode()}\n";
$response = $middleware2->process(new ServerRequest('GET', 'https://globex.example.com/'), new EchoHandler());
echo "suspended -> {$response->getStatusCode()}\n";
