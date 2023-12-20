<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Injector is able to analyze callable dependencies based on type hinting and
 * inject them from any PSR-11 compatible container.
 */
final class Injector
{
    private ?ContainerInterface $container;
    private bool $cacheReflections = false;

    /**
     * @var ReflectionClass[]
     * @psalm-var array<class-string,ReflectionClass>
     */
    private array $reflectionsCache = [];

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Enable memoization of class reflections for improved performance when resolving the same objects multiple times.
     * Note: Enabling this feature may increase memory usage.
     */
    public function withCacheReflections(bool $cacheReflections = true): self
    {
        $new = clone $this;
        $new->cacheReflections = $cacheReflections;
        return $new;
    }

    /**
     * Invoke a callback with resolving dependencies based on parameter types.
     *
     * This methods allows invoking a callback and let type hinted parameter names to be
     * resolved as objects of the Container. It additionally allow calling function passing named arguments.
     *
     * For example, the following callback may be invoked using the Container to resolve the formatter dependency:
     *
     * ```php
     * $formatString = function($string, \Yiisoft\I18n\MessageFormatterInterface $formatter) {
     *    // ...
     * }
     *
     * $injector = new Yiisoft\Injector\Injector($container);
     * $injector->invoke($formatString, ['string' => 'Hello World!']);
     * ```
     *
     * This will pass the string `'Hello World!'` as the first argument, and a formatter instance created
     * by the DI container as the second argument.
     *
     * @param callable $callable callable to be invoked.
     * @param array $arguments The array of the function arguments.
     * This can be either a list of arguments, or an associative array where keys are argument names.
     *
     * @throws MissingRequiredArgumentException if required argument is missing.
     * @throws ContainerExceptionInterface if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws ReflectionException
     *
     * @return mixed The callable return value.
     */
    public function invoke(callable $callable, array $arguments = [])
    {
        $callable = Closure::fromCallable($callable);
        $reflection = new ReflectionFunction($callable);
        return $reflection->invokeArgs($this->resolveDependencies($reflection, $arguments));
    }

    /**
     * Creates an object of a given class with resolving constructor dependencies based on parameter types.
     *
     * This methods allows invoking a constructor and let type hinted parameter names to be
     * resolved as objects of the Container. It additionally allow calling constructor passing named arguments.
     *
     * For example, the following constructor may be invoked using the Container to resolve the formatter dependency:
     *
     * ```php
     * class StringFormatter
     * {
     *     public function __construct($string, \Yiisoft\I18n\MessageFormatterInterface $formatter)
     *     {
     *         // ...
     *     }
     * }
     *
     * $injector = new Yiisoft\Injector\Injector($container);
     * $stringFormatter = $injector->make(StringFormatter::class, ['string' => 'Hello World!']);
     * ```
     *
     * This will pass the string `'Hello World!'` as the first argument, and a formatter instance created
     * by the DI container as the second argument.
     *
     * @param string $class name of the class to be created.
     * @param array $arguments The array of the function arguments.
     * This can be either a list of arguments, or an associative array where keys are argument names.
     *
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException|MissingRequiredArgumentException
     * @throws ReflectionException
     *
     * @return object The object of the given class.
     *
     * @psalm-suppress MixedMethodCall
     *
     * @psalm-template T
     * @psalm-param class-string<T> $class
     * @psalm-return T
     */
    public function make(string $class, array $arguments = []): object
    {
        $classReflection = $this->getClassReflection($class);
        if (!$classReflection->isInstantiable()) {
            throw new \InvalidArgumentException("Class $class is not instantiable.");
        }
        $reflection = $classReflection->getConstructor();
        if ($reflection === null) {
            // Method __construct() does not exist
            return new $class();
        }

        return new $class(...$this->resolveDependencies($reflection, $arguments));
    }

    /**
     * Resolve dependencies for the given function reflection object and a list of concrete arguments
     * and return array of arguments to call the function with.
     *
     * @param ReflectionFunctionAbstract $reflection function reflection.
     * @param array $arguments concrete arguments.
     *
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException|MissingRequiredArgumentException
     * @throws ReflectionException
     *
     * @return array resolved arguments.
     */
    private function resolveDependencies(ReflectionFunctionAbstract $reflection, array $arguments = []): array
    {
        $state = new ResolvingState($reflection, $arguments);

        $isInternalOptional = false;
        $internalParameter = '';
        foreach ($reflection->getParameters() as $parameter) {
            if ($isInternalOptional) {
                // Check custom parameter definition for an internal function
                if ($state->hasNamedArgument($parameter->getName())) {
                    throw new MissingInternalArgumentException($reflection, $internalParameter);
                }
                continue;
            }
            // Resolve parameter
            $resolved = $this->resolveParameter($parameter, $state);
            if ($resolved === true) {
                continue;
            }

            if ($resolved === false) {
                throw new MissingRequiredArgumentException($reflection, $parameter->getName());
            }
            // Internal function. Parameter not resolved
            $isInternalOptional = true;
            $internalParameter = $parameter->getName();
        }

        return $state->getResolvedValues();
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     *
     * @return bool|null True if argument resolved; False if not resolved; Null if parameter is optional but
     * without default value in a Reflection object. This is possible for internal functions.
     */
    private function resolveParameter(ReflectionParameter $parameter, ResolvingState $state): ?bool
    {
        $name = $parameter->getName();
        $isVariadic = $parameter->isVariadic();
        $hasType = $parameter->hasType();
        $state->disablePushTrailingArguments($isVariadic && $hasType);

        // Try to resolve parameter by name
        if ($state->resolveParameterByName($name, $isVariadic)) {
            return true;
        }

        $error = null;

        if ($hasType) {
            /** @var ReflectionType $reflectionType */
            $reflectionType = $parameter->getType();

            if ($this->resolveParameterType($state, $reflectionType, $isVariadic, $error)) {
                return true;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            $argument = $parameter->getDefaultValue();
            $state->addResolvedValue($argument);
            return true;
        }

        if (!$parameter->isOptional()) {
            if ($hasType && $parameter->allowsNull()) {
                $argument = null;
                $state->addResolvedValue($argument);
                return true;
            }

            if ($error === null) {
                return false;
            }

            // Throw NotFoundExceptionInterface
            throw $error;
        }

        if ($isVariadic) {
            return true;
        }
        return null;
    }

    /**
     * Resolve parameter using its type.
     *
     * @param NotFoundExceptionInterface|null $error Last caught {@see NotFoundExceptionInterface} exception.
     *
     * @throws ContainerExceptionInterface
     *
     * @return bool True if argument was resolved
     *
     * @psalm-suppress PossiblyUndefinedMethod
     */
    private function resolveParameterType(
        ResolvingState $state,
        ReflectionType $type,
        bool $variadic,
        ?NotFoundExceptionInterface &$error
    ): bool {
        switch (true) {
            case $type instanceof ReflectionNamedType:
                $types = [$type];
                // no break
            case $type instanceof ReflectionUnionType:
                $types ??= $type->getTypes();
                /** @var array<int, ReflectionNamedType> $types */
                foreach ($types as $namedType) {
                    try {
                        if ($this->resolveNamedType($state, $namedType, $variadic)) {
                            return true;
                        }
                    } catch (NotFoundExceptionInterface $e) {
                        $error = $e;
                    }
                }
                break;
            case $type instanceof ReflectionIntersectionType:
                $classes = [];
                /** @var ReflectionNamedType $namedType */
                foreach ($type->getTypes() as $namedType) {
                    $classes[] = $namedType->getName();
                }
                /** @var array<int, class-string> $classes */
                if ($state->resolveParameterByClasses($classes, $variadic)) {
                    return true;
                }
                break;
        }
        return false;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @return bool True if argument was resolved
     */
    private function resolveNamedType(ResolvingState $state, ReflectionNamedType $parameter, bool $isVariadic): bool
    {
        $type = $parameter->getName();
        /** @psalm-var class-string|null $class */
        $class = $parameter->isBuiltin() ? null : $type;
        $isClass = $class !== null || $type === 'object';
        return $isClass && $this->resolveObjectParameter($state, $class, $isVariadic);
    }

    /**
     * @psalm-param class-string|null $class
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @return bool True if argument resolved
     */
    private function resolveObjectParameter(ResolvingState $state, ?string $class, bool $isVariadic): bool
    {
        $found = $state->resolveParameterByClass($class, $isVariadic);
        if ($found || $isVariadic) {
            return $found;
        }
        if ($class !== null && $this->container !== null) {
            $argument = $this->container->get($class);
            $state->addResolvedValue($argument);
            return true;
        }
        return false;
    }

    /**
     * @psalm-param class-string $class
     *
     * @throws ReflectionException
     */
    private function getClassReflection(string $class): ReflectionClass
    {
        if ($this->cacheReflections) {
            return $this->reflectionsCache[$class] ??= new ReflectionClass($class);
        }

        return new ReflectionClass($class);
    }
}
