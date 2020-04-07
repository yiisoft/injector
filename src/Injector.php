<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

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
        if (\is_object($callable) && !$callable instanceof \Closure) {
            $callable = [$callable, '__invoke'];
        }

        if (\is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } else {
            $reflection = new \ReflectionFunction($callable);
        }

        return \call_user_func_array($callable, $this->resolveDependencies($reflection, $arguments));
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
     * @param \ReflectionFunctionAbstract $reflection function reflection.
     * @param array $arguments concrete arguments.
     * @return array resolved arguments.
     * @throws ContainerExceptionInterface
     * @throws MissingRequiredArgumentException|InvalidArgumentException
     * @throws ReflectionException
     */
    private function resolveDependencies(\ReflectionFunctionAbstract $reflection, array $arguments = []): array
    {
        $resolvedArguments = [];

        $pushUnusedArguments = true;
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $class = $parameter->getClass();
            $hasType = $parameter->hasType();
            $isNullable = $parameter->allowsNull() && $hasType;
            $isVariadic = $parameter->isVariadic();
            $error = null;
            unset($tmpValue);

            // Get argument by name
            if (array_key_exists($name, $arguments)) {
                if ($isVariadic && is_array($arguments[$name])) {
                    $resolvedArguments = array_merge($resolvedArguments, array_values($arguments[$name]));
                } else {
                    $resolvedArguments[] = &$arguments[$name];
                }
                unset($arguments[$name]);
                continue;
            }

            $type = $hasType ? $parameter->getType()->getName() : null;
            if ($class !== null || $type === 'object') {
                // Unnamed arguments
                $className = $class !== null ? $class->getName() : null;
                $found = false;
                foreach ($arguments as $key => $item) {
                    if (!is_int($key)) {
                        continue;
                    }
                    if (is_object($item) and $className === null || $item instanceof $className) {
                        $found = true;
                        $resolvedArguments[] = &$arguments[$key];
                        unset($arguments[$key], $item);
                        if (!$isVariadic) {
                            break;
                        }
                    }
                }
                if ($found) {
                    $pushUnusedArguments = false;
                    continue;
                }

                if ($className !== null) {
                    // If the argument is optional we catch not instantiable exceptions
                    try {
                        $tmpValue = $this->container->get($className);
                        $resolvedArguments[] = &$tmpValue;
                        continue;
                    } catch (NotFoundExceptionInterface $e) {
                        $error = $e;
                    }
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $tmpValue = $parameter->getDefaultValue();
                $resolvedArguments[] = &$tmpValue;
            } elseif (!$parameter->isOptional()) {
                if ($isNullable) {
                    $tmpValue = null;
                    $resolvedArguments[] = &$tmpValue;
                } else {
                    throw $error ?? new MissingRequiredArgumentException($name, $reflection->getName());
                }
            } elseif ($hasType) {
                $pushUnusedArguments = false;
            }
        }

        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                if (!is_object($value)) {
                    throw new InvalidArgumentException((string)$key, $reflection->getName());
                }
                if ($pushUnusedArguments) {
                    $resolvedArguments[] = $value;
                }
            }
        }
        return $resolvedArguments;
    }
}
