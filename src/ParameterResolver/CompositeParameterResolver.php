<?php

declare(strict_types=1);

namespace Yiisoft\Injector\ParameterResolver;

use ReflectionParameter;

final class CompositeParameterResolver implements ParameterResolverInterface
{
    private array $resolvers;

    public function __construct(ParameterResolverInterface ...$resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function resolve(ReflectionParameter $parameter)
    {
        foreach ($this->resolvers as $resolver) {
            try {
                return $resolver->resolve($parameter);
            } catch (ParameterNotResolvedException $e) {
            }
        }

        throw new ParameterNotResolvedException();
    }
}
