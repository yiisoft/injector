<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Generator;
use ReflectionFunctionAbstract;

final class ResolvingState
{
    private ReflectionFunctionAbstract $reflection;
    /** @var array<int, object> */
    private array $numericArgs = [];
    /** @var array<string, mixed> */
    private array $namedArgs = [];
    private bool $isPushTrailedArguments;
    private array $resolvedValues = [];

    /**
     * @param ReflectionFunctionAbstract $reflection function reflection.
     * @param array $arguments user arguments.
     */
    public function __construct(ReflectionFunctionAbstract $reflection, array $arguments)
    {
        $this->reflection = $reflection;
        $this->isPushTrailedArguments = !$reflection->isInternal();
        $this->sortArguments($arguments);
    }

    public function hasNamedArgument(string $name): bool
    {
        return array_key_exists($name, $this->namedArgs);
    }

    /**
     * @param bool $condition If true then trailed arguments will not be passed
     */
    public function disableTrailedArguments(bool $condition): void
    {
        $this->isPushTrailedArguments = $this->isPushTrailedArguments && !$condition;
    }

    public function addResolvedValue(&$value): void
    {
        $this->resolvedValues[] = &$value;
    }
    public function resolveParamByName(string $name, bool $variadic): bool
    {
        if (!array_key_exists($name, $this->namedArgs)) {
            return false;
        }
        if ($variadic && is_array($this->namedArgs[$name])) {
            array_walk($this->namedArgs[$name], [$this, 'addResolvedValue']);
        } else {
            $this->addResolvedValue($this->namedArgs[$name]);
        }
        return true;
    }
    public function resolveParamByClass(?string $className, bool $variadic): bool
    {
        $generator = $this->pullNumericArg($className);
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

    public function getResolvedValues(): array
    {
        return $this->isPushTrailedArguments
            ? [...$this->resolvedValues, ...$this->numericArgs]
            : $this->resolvedValues;
    }

    /**
     * @param null|string $className
     * @return Generator<int, object>
     */
    private function &pullNumericArg(?string $className): Generator
    {
        foreach ($this->numericArgs as $key => &$value) {
            if ($className === null || $value instanceof $className) {
                unset($this->numericArgs[$key]);
                yield $value;
            }
        }
    }
    /**
     * @param ReflectionFunctionAbstract $reflection
     * @param array $arguments
     * @throws InvalidArgumentException
     */
    private function sortArguments(array $arguments): void
    {
        foreach ($arguments as $key => &$value) {
            if (is_int($key)) {
                if (!is_object($value)) {
                    throw new InvalidArgumentException($this->reflection, (string)$key);
                }
                $this->numericArgs[] = &$value;
            } else {
                $this->namedArgs[$key] = &$value;
            }
        }
    }
}
