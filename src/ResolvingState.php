<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Generator;
use ReflectionFunctionAbstract;

/**
 * Intermediate arguments resolving data to pass around until resolving is finished.
 *
 * @internal
 */
final class ResolvingState
{
    private ReflectionFunctionAbstract $reflection;

    /**
     * @psalm-var array<int, object>
     */
    private array $numericArguments = [];

    /**
     * @psalm-var array<string, mixed>
     */
    private array $namedArguments = [];

    private bool $shouldPushTrailingArguments;

    /**
     * @psalm-var list<mixed>
     */
    private array $resolvedValues = [];

    /**
     * @param ReflectionFunctionAbstract $reflection Function reflection.
     * @param array $arguments User arguments.
     */
    public function __construct(ReflectionFunctionAbstract $reflection, array $arguments)
    {
        $this->reflection = $reflection;
        $this->shouldPushTrailingArguments = !$reflection->isInternal();
        $this->sortArguments($arguments);
    }

    public function hasNamedArgument(string $name): bool
    {
        return array_key_exists($name, $this->namedArguments);
    }

    /**
     * @param bool $condition If true then trailing arguments will not be passed.
     */
    public function disablePushTrailingArguments(bool $condition): void
    {
        $this->shouldPushTrailingArguments = $this->shouldPushTrailingArguments && !$condition;
    }

    /**
     * @param mixed $value
     */
    public function addResolvedValue(&$value): void
    {
        /** @psalm-suppress UnsupportedReferenceUsage */
        $this->resolvedValues[] = &$value;
    }

    public function resolveParameterByName(string $name, bool $variadic): bool
    {
        if (!array_key_exists($name, $this->namedArguments)) {
            return false;
        }
        if ($variadic && is_array($this->namedArguments[$name])) {
            array_walk($this->namedArguments[$name], [$this, 'addResolvedValue']);
        } else {
            $this->addResolvedValue($this->namedArguments[$name]);
        }
        return true;
    }

    /**
     * @psalm-param class-string|null $className
     */
    public function resolveParameterByClass(?string $className, bool $variadic): bool
    {
        $generator = $this->pullNumericArgument($className);
        if (!$variadic) {
            if (!$generator->valid()) {
                return false;
            }
            $value = $generator->current();
            $this->addResolvedValue($value);
            return true;
        }
        foreach ($generator as &$value) {
            $this->addResolvedValue($value);
        }
        return true;
    }

    /**
     * Resolve parameter using type intersection rules.
     *
     * @psalm-param array<int, class-string> $classNames
     */
    public function resolveParameterByClasses(array $classNames, bool $variadic): bool
    {
        $resolved = false;
        foreach ($this->numericArguments as $key => &$argument) {
            foreach ($classNames as $class) {
                if (!$argument instanceof $class) {
                    continue 2;
                }
            }
            unset($this->numericArguments[$key]);
            $this->addResolvedValue($argument);
            if (!$variadic) {
                return true;
            }
            $resolved = true;
        }

        return $resolved;
    }

    public function getResolvedValues(): array
    {
        return $this->shouldPushTrailingArguments
            ? [...$this->resolvedValues, ...$this->numericArguments]
            : $this->resolvedValues;
    }

    /**
     * @psalm-param class-string|null $className
     *
     * @psalm-return Generator<int, object, mixed, void>
     */
    private function &pullNumericArgument(?string $className): Generator
    {
        foreach ($this->numericArguments as $key => &$value) {
            if ($className === null || $value instanceof $className) {
                unset($this->numericArguments[$key]);
                yield $value;
            }
        }
    }

    /**
     * @param array $arguments
     *
     * @throws InvalidArgumentException
     */
    private function sortArguments(array $arguments): void
    {
        foreach ($arguments as $key => &$value) {
            if (is_int($key)) {
                if (!is_object($value)) {
                    throw new InvalidArgumentException($this->reflection, (string)$key);
                }
                /** @psalm-suppress UnsupportedReferenceUsage */
                $this->numericArguments[] = &$value;
            } else {
                $this->namedArguments[$key] = &$value;
            }
        }
    }
}
