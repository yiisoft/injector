<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use ReflectionFunctionAbstract;

final class ResolvingState
{
    public ReflectionFunctionAbstract $reflection;
    public array $arguments;
    private bool $isPushTrailedArguments;
    private array $resolvedValues = [];

    /**
     * @param ReflectionFunctionAbstract $reflection function reflection.
     * @param array $arguments user arguments.
     */
    public function __construct(ReflectionFunctionAbstract $reflection, array $arguments)
    {
        $this->reflection = $reflection;
        $this->arguments = $arguments;
        $this->isPushTrailedArguments = !$reflection->isInternal();
    }

    public function addValue(&$value): void
    {
        $this->resolvedValues[] = &$value;
    }

    public function getValues(): array
    {
        return $this->isPushTrailedArguments
            ? [...$this->resolvedValues, ...array_filter($this->arguments, 'is_int', ARRAY_FILTER_USE_KEY)]
            : $this->resolvedValues;
    }

    /**
     * @param bool $condition If true then trailed arguments will not be passed
     */
    public function disableTrailedArguments(bool $condition): void
    {
        $this->isPushTrailedArguments = $this->isPushTrailedArguments && !$condition;
    }
}
