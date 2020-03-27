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
     * Invoke a callback with resolving dependencies in parameters.
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
     * $container->invoke($formatString, ['string' => 'Hello World!']);
     * ```
     *
     * This will pass the string `'Hello World!'` as the first argument, and a formatter instance created
     * by the DI container as the second argument.
     *
     * @param callable $callable callable to be invoked.
     * @param array $arguments The array of the function arguments.
     * This can be either a list of arguments, or an associative array where keys are argument names.
     * @return mixed the callable return value.
     * @throws MissingRequiredArgumentException  if required argument is missing.
     * @throws ContainerExceptionInterface if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws ReflectionException
     */
    public function invoke(callable $callable, array $arguments = [])
    {
        return \call_user_func_array($callable, $this->resolveCallableDependencies($callable, $arguments));
    }

    /**
     * Resolve dependencies for a function.
     *
     * This method can be used to implement similar functionality as provided by [[invoke()]] in other
     * components.
     *
     * @param callable $callback callable to be invoked.
     * @param array $arguments The array of the function arguments, can be either numeric or associative.
     * @return array The resolved dependencies.
     * @throws MissingRequiredArgumentException if required argument is missing.
     * @throws ContainerExceptionInterface if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws ReflectionException
     */
    private function resolveCallableDependencies(callable $callback, array $arguments = []): array
    {
        if (\is_object($callback) && !$callback instanceof \Closure) {
            $callback = [$callback, '__invoke'];
        }

        if (\is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $resolvedArguments = [];

        $pushUnusedArguments = true;
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            $class = $parameter->getClass();
            $hasType = $parameter->hasType();
            $isNullable = $parameter->allowsNull() && $hasType;
            $isVariadic = $parameter->isVariadic();
            $error = null;

            // Get argument by name
            if (array_key_exists($name, $arguments)) {
                if ($isVariadic && is_array($arguments[$name])) {
                    $resolvedArguments = array_merge($resolvedArguments, array_values($arguments[$name]));
                } else {
                    $resolvedArguments[] = $arguments[$name];
                }
                unset($arguments[$name]);
                continue;
            }

            if ($class !== null) {
                // Unnamed arguments
                $className = $class->getName();
                $found = false;
                foreach ($arguments as $key => $item) {
                    if (!is_int($key)) {
                        continue;
                    }
                    if ($item instanceof $className) {
                        $found = true;
                        $resolvedArguments[] = $item;
                        unset($arguments[$key]);
                        if (!$isVariadic) {
                            break;
                        }
                    }
                }
                if ($found) {
                    $pushUnusedArguments = false;
                    continue;
                }

                // If the argument is optional we catch not instantiable exceptions
                try {
                    $resolvedArguments[] = $this->container->get($className);
                    continue;
                } catch (NotFoundExceptionInterface $e) {
                    $error = $e;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolvedArguments[] = $parameter->getDefaultValue();
            } elseif (!$parameter->isOptional()) {
                if ($isNullable) {
                    $resolvedArguments[] = null;
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
