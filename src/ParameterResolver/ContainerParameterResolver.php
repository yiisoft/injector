<?php

declare(strict_types=1);

namespace Yiisoft\Injector\ParameterResolver;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class ContainerParameterResolver implements ParameterResolverInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws ParameterNotResolvedException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function resolve(ReflectionParameter $parameter)
    {
        if ($parameter->isVariadic()) {
            throw new ParameterNotResolvedException();
        }

        $hasType = $parameter->hasType();

        if ($hasType) {
            $reflectionType = $parameter->getType();
            if ($reflectionType === null) {
                throw new ParameterNotResolvedException();
            }
            return $this->resolveType($reflectionType);
        }

        throw new ParameterNotResolvedException();
    }

    /**
     * @return mixed
     *
     * @throws ParameterNotResolvedException
     * @throws ContainerExceptionInterface
     */
    private function resolveType(ReflectionType $type)
    {
        if ($type instanceof ReflectionNamedType) {
            return $this->resolveNamedType($type);
        }

        if ($type instanceof ReflectionUnionType) {
            /** @var ReflectionNamedType $namedType */
            foreach ($type->getTypes() as $namedType) {
                try {
                    return $this->resolveNamedType($namedType);
                } catch (ParameterNotResolvedException $e) {
                }
            }
        }

        throw new ParameterNotResolvedException();
    }

    /**
     * @return mixed
     *
     * @throws ParameterNotResolvedException
     * @throws ContainerExceptionInterface
     */
    private function resolveNamedType(ReflectionNamedType $parameter)
    {
        if ($parameter->isBuiltin()) {
            throw new ParameterNotResolvedException();
        }

        try {
            return $this->container->get($parameter->getName());
        } catch (NotFoundExceptionInterface $e) {
            throw new ParameterNotResolvedException();
        }
    }
}
