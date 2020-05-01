<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionParameter;

/**
 * Injector is able to analyze callable dependencies based on
 * type hinting and inject them from any PSR-11 compatible container.
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
        $callable = \Closure::fromCallable($callable);
        $reflection = new \ReflectionFunction($callable);
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
        $classReflection = new \ReflectionClass($class);
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
        $resolvedArguments = [];
        $pushUnusedArguments = true;
        $isInternalOptional = false;
        $internalParameter = '';
        foreach ($reflection->getParameters() as $parameter) {
            if ($isInternalOptional) {
                // Check custom parameter definition for an internal function
                if (array_key_exists($parameter->getName(), $arguments)) {
                    throw new MissingInternalArgumentException($reflection, $internalParameter);
                }
                continue;
            }
            // Resolve parameter
            $resolved = $this->resolveParameter($parameter, $resolvedArguments, $arguments, $pushUnusedArguments);
            if ($resolved === true) {
                continue;
            }

            if ($resolved === false) {
                throw new MissingRequiredArgumentException($reflection, $parameter->getName());
            }
            // Internal function. Parameter not resolved
            $isInternalOptional = true;
            $internalParameter = $parameter->getName();
            if (count($arguments) === 0) {
                break;
            }
        }

        $this->checkNumericKeyArguments($reflection, $arguments);

        return $pushUnusedArguments
            ? [...$resolvedArguments, ...array_filter($arguments, 'is_int', ARRAY_FILTER_USE_KEY)]
            : $resolvedArguments;
    }

    /**
     * @param ReflectionParameter $parameter
     * @param array $resolvedArguments
     * @param array $arguments
     * @param bool $pushUnusedArguments
     * @return null|bool True if argument resolved; False if not resolved; Null if parameter is optional but without
     * default value in a Reflection object. This is possible for internal functions.
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        array &$resolvedArguments,
        array &$arguments,
        bool &$pushUnusedArguments
    ): ?bool {
        $name = $parameter->getName();
        $isVariadic = $parameter->isVariadic();

        // Get argument by name
        if (array_key_exists($name, $arguments)) {
            if ($isVariadic && is_array($arguments[$name])) {
                $resolvedArguments = array_merge($resolvedArguments, array_values($arguments[$name]));
            } else {
                $resolvedArguments[] = &$arguments[$name];
            }
            unset($arguments[$name]);
            return true;
        }

        $class = $parameter->getClass();
        $hasType = $parameter->hasType();
        $type = $hasType ? $parameter->getType()->getName() : null;
        $error = null;
        if ($class !== null || $type === 'object') {
            // Unnamed arguments
            $className = $class !== null ? $class->getName() : null;
            $found = false;
            foreach ($arguments as $key => $item) {
                if (!is_int($key)) {
                    continue;
                }
                if (is_object($item) && ($className === null || $item instanceof $className)) {
                    $found = true;
                    $resolvedArguments[] = &$arguments[$key];
                    unset($arguments[$key]);
                    if (!$isVariadic) {
                        return true;
                    }
                }
            }
            if ($found) {
                // Only for variadic arguments
                $pushUnusedArguments = false;
                return true;
            }

            if ($className !== null) {
                // If the argument is optional we catch not instantiable exceptions
                try {
                    $argument = $this->container->get($className);
                    $resolvedArguments[] = &$argument;
                    return true;
                } catch (NotFoundExceptionInterface $e) {
                    $error = $e;
                }
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            $argument = $parameter->getDefaultValue();
            $resolvedArguments[] = &$argument;
            return true;
        }

        if (!$parameter->isOptional()) {
            if ($hasType && $parameter->allowsNull()) {
                $argument = null;
                $resolvedArguments[] = &$argument;
                return true;
            }

            if ($error === null) {
                return false;
            }

            // Throw container exception
            throw $error;
        }

        if ($isVariadic) {
            $pushUnusedArguments = !$hasType && $pushUnusedArguments;
            return true;
        }

        // Internal function with optional params
        $pushUnusedArguments = false;
        return null;
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @param array $arguments
     * @throws InvalidArgumentException
     */
    private function checkNumericKeyArguments(ReflectionFunctionAbstract $reflection, array &$arguments): void
    {
        foreach ($arguments as $key => $value) {
            if (is_int($key) && !is_object($value)) {
                throw new InvalidArgumentException($reflection, (string)$key);
            }
        }
    }
}
