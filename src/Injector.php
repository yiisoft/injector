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
class Injector
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
     * resolved as objects of the Container. It additionally allow calling function using named parameters.
     *
     * For example, the following callback may be invoked using the Container to resolve the formatter dependency:
     *
     * ```php
     * $formatString = function($string, \yii\i18n\Formatter $formatter) {
     *    // ...
     * }
     * $container->invoke($formatString, ['string' => 'Hello World!']);
     * ```
     *
     * This will pass the string `'Hello World!'` as the first param, and a formatter instance created
     * by the DI container as the second param to the callable.
     *
     * @param callable $callback callable to be invoked.
     * @param array $params The array of parameters for the function.
     * This can be either a list of parameters, or an associative array representing named function parameters.
     * @return mixed the callback return value.
     * @throws MissingRequiredArgumentException  if required argument is missing.
     * @throws ContainerExceptionInterface if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws ReflectionException
     */
    public function invoke(callable $callback, array $params = [])
    {
        return \call_user_func_array($callback, $this->resolveCallableDependencies($callback, $params));
    }

    /**
     * Resolve dependencies for a function.
     *
     * This method can be used to implement similar functionality as provided by [[invoke()]] in other
     * components.
     *
     * @param callable $callback callable to be invoked.
     * @param array $parameters The array of parameters for the function, can be either numeric or associative.
     * @return array The resolved dependencies.
     * @throws MissingRequiredArgumentException if required argument is missing.
     * @throws ContainerExceptionInterface if a dependency cannot be resolved or if a dependency cannot be fulfilled.
     * @throws ReflectionException
     */
    private function resolveCallableDependencies(callable $callback, array $parameters = []): array
    {
        if (\is_object($callback) && !$callback instanceof \Closure) {
            $callback = [$callback, '__invoke'];
        }

        if (\is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
        } else {
            $reflection = new \ReflectionFunction($callback);
        }

        $arguments = [];

        $pushUnusedParams = true;
        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $class = $param->getClass();
            $hasType = $param->hasType();
            $nullable = $param->allowsNull() && $hasType;
            $variadic = $param->isVariadic();
            $error = null;

            // Get argument by name
            if (key_exists($name, $parameters)) {
                if ($variadic && is_array($parameters[$name])) {
                    $arguments = array_merge($arguments, array_values($parameters[$name]));
                } else {
                    $arguments[] = $parameters[$name];
                }
                unset($parameters[$name]);
                continue;
            }

            if ($class !== null) {
                // Unnamed parameters
                $className = $class->getName();
                $found = false;
                foreach ($parameters as $key => $item) {
                    if (!is_int($key)) {
                        continue;
                    }
                    if ($item instanceof $className) {
                        $found = true;
                        $arguments[] = $item;
                        unset($parameters[$key]);
                        if (!$variadic) {
                            break;
                        }
                    }
                }
                if ($found) {
                    $pushUnusedParams = false;
                    continue;
                }

                // If the argument is optional we catch not instantiable exceptions
                try {
                    $arguments[] = $this->container->get($className);
                    continue;
                } catch (NotFoundExceptionInterface $e) {
                    $error = $e;
                }
            }

            if ($param->isDefaultValueAvailable()) {
                $arguments[] = $param->getDefaultValue();
            } elseif (!$param->isOptional()) {
                if ($nullable) {
                    $arguments[] = null;
                } else {
                    throw $error ?? new MissingRequiredArgumentException($name, $reflection->getName());
                }
            } elseif ($hasType) {
                $pushUnusedParams = false;
            }
        }

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                if (!is_object($value)) {
                    throw new InvalidParameterException((string)$key, $reflection->getName());
                }
                if ($pushUnusedParams) {
                    $arguments[] = $value;
                }
            }
        }
        return $arguments;
    }
}
