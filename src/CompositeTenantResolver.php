<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Tries resolvers in order and returns the first non-null key.
 *
 * @api
 */
final readonly class CompositeTenantResolver implements TenantResolver
{
    /**
     * @var list<TenantResolver>
     */
    private array $resolvers;

    public function __construct(TenantResolver ...$resolvers)
    {
        $this->resolvers = array_values($resolvers);
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $key = $resolver->resolve($request);

            if ($key !== null) {
                return $key;
            }
        }

        return null;
    }
}
