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
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Injector is able to analyze callable dependencies based on type hinting and
 * inject them from any PSR-11 compatible container.
 */
final class Injector
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
     * @return mixed the callable return value.
     * @throws MissingRequiredArgumentException if required argument is missing.
     * @throws ContainerExceptionInterface if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws ReflectionException
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
     * @return mixed object of the given class.
     * @throws ContainerExceptionInterface
     * @throws MissingRequiredArgumentException|InvalidArgumentException
     * @throws ReflectionException
     */
    public function make(string $class, array $arguments = [])
    {
        $classReflection = new ReflectionClass($class);
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
     * @return array resolved arguments.
     * @throws ContainerExceptionInterface
     * @throws MissingRequiredArgumentException|InvalidArgumentException
     * @throws ReflectionException
     */
    private function resolveDependencies(ReflectionFunctionAbstract $reflection, array $arguments = []): array
    {
        $this->checkNumericKeyArguments($reflection, $arguments);
        $state = new ResolvingState($reflection, $arguments);

        $isInternalOptional = false;
        $internalParameter = '';
        foreach ($reflection->getParameters() as $parameter) {
            if ($isInternalOptional) {
                // Check custom parameter definition for an internal function
                if (array_key_exists($parameter->getName(), $state->arguments)) {
                    throw new MissingInternalArgumentException($state->reflection, $internalParameter);
                }
                continue;
            }
            // Resolve parameter
            $resolved = $this->resolveParameter($parameter, $state);
            if ($resolved === true) {
                continue;
            }

            if ($resolved === false) {
                throw new MissingRequiredArgumentException($state->reflection, $parameter->getName());
            }
            // Internal function. Parameter not resolved
            $isInternalOptional = true;
            $internalParameter = $parameter->getName();
        }

        return $state->getValues();
    }

    /**
     * @param ReflectionParameter $parameter
     * @param ResolvingState $state
     * @return null|bool True if argument resolved; False if not resolved; Null if parameter is optional but without
     * default value in a Reflection object. This is possible for internal functions.
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function resolveParameter(ReflectionParameter $parameter, ResolvingState $state): ?bool {
        $name = $parameter->getName();
        $isVariadic = $parameter->isVariadic();
        $hasType = $parameter->hasType();
        $state->disableTrailedArguments($isVariadic && $hasType);

        // Get argument by name
        if (array_key_exists($name, $state->arguments)) {
            if ($isVariadic && is_array($state->arguments[$name])) {
                array_walk($state->arguments[$name], [$state, 'addValue']);
            } else {
                $state->addValue($state->arguments[$name]);
            }
            return true;
        }

        $error = null;

        if ($hasType) {
            $reflectionType = $parameter->getType();

            // $reflectionType may be instance of ReflectionUnionType (php8)
            /** @phan-suppress-next-line PhanUndeclaredMethod */
            $types = $reflectionType instanceof ReflectionNamedType ? [$reflectionType] : $reflectionType->getTypes();
            foreach ($types as $namedType) {
                try {
                    if ($this->resolveNamedType($state, $namedType, $isVariadic)) {
                        return true;
                    }
                } catch (NotFoundExceptionInterface $e) {
                    $error = $e;
                }
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            $argument = $parameter->getDefaultValue();
            $state->addValue($argument);
            return true;
        }

        if (!$parameter->isOptional()) {
            if ($hasType && $parameter->allowsNull()) {
                $argument = null;
                $state->addValue($argument);
                return true;
            }

            if ($error === null) {
                return false;
            }

            // Throw container exception
            throw $error;
        }

        if ($isVariadic) {
            return true;
        }

        // Internal function with optional params
        $state->disableTrailedArguments(true);
        return null;
    }

    /**
     * @param ResolvingState $state
     * @param ReflectionNamedType $parameter
     * @param bool $isVariadic
     * @return bool True if argument was resolved
     */
    private function resolveNamedType(ResolvingState $state, ReflectionNamedType $parameter, bool $isVariadic): bool
    {
        $type = $parameter->getName();
        $class = $parameter->isBuiltin() ? null : $type;
        $isClass = $class !== null || $type === 'object';
        return $isClass && $this->resolveObjectParameter($state, $class, $isVariadic);
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @param array $arguments
     * @throws InvalidArgumentException
     */
    private function checkNumericKeyArguments(ReflectionFunctionAbstract $reflection, array $arguments): void
    {
        foreach ($arguments as $key => $value) {
            if (is_int($key) && !is_object($value)) {
                throw new InvalidArgumentException($reflection, (string)$key);
            }
        }
    }

    /**
     * @param ResolvingState $state
     * @param null|string $class
     * @param bool $isVariadic
     * @return bool True if argument resolved
     * @throws ContainerExceptionInterface
     */
    private function resolveObjectParameter(ResolvingState $state, ?string $class, bool $isVariadic): bool
    {
        $found = $this->findObjectArguments($state, $class, $isVariadic);
        if ($found || $isVariadic) {
            return $found;
        }
        if ($class !== null) {
            $argument = $this->container->get($class);
            $state->addValue($argument);
            return true;
        }
        return false;
    }

    /**
     * @param ResolvingState $state
     * @param null|string $className Null value means objects of any class
     * @param bool $multiple
     * @return bool True if arguments were found
     */
    private function findObjectArguments(ResolvingState $state, ?string $className, bool $multiple): bool
    {
        $found = false;
        foreach ($state->arguments as $key => $item) {
            if (is_int($key) && is_object($item) && ($className === null || $item instanceof $className)) {
                $state->addValue($state->arguments[$key]);
                unset($state->arguments[$key]);
                if (!$multiple) {
                    return true;
                }
                $found = true;
            }
        }
        return $found;
    }
}
