<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Generator;
use ReflectionFunctionAbstract;

/**
 * Intermediate arguments resolving data to pass around until resolving is finished.
 * @internal
 */
final class ResolvingState
{
    private ReflectionFunctionAbstract $reflection;
    /** @var array<int, object> */
    private array $numericArguments = [];
    /** @var array<string, mixed> */
    private array $namedArguments = [];
    private bool $shouldPushTrailingArguments;
    private array $resolvedValues = [];
    /** @var null|array<string, string> */
    private ?array $templateData = null;

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

    public function addResolvedValue(&$value): void
    {
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

    public function getResolvedValues(): array
    {
        return $this->shouldPushTrailingArguments
            ? [...$this->resolvedValues, ...$this->numericArguments]
            : $this->resolvedValues;
    }
    /**
     * @return array<string, string>
     * @phan-suppress PhanPossiblyNullTypeReturn
     */
    public function getDataToTemplate(): array
    {
        if ($this->templateData === null) {
            $this->prepareDataToTemplate();
        }
        return $this->templateData;
    }

    /**
     * @param null|string $className
     * @return Generator<void, object>
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
     * @throws InvalidArgumentException
     */
    private function sortArguments(array $arguments): void
    {
        foreach ($arguments as $key => &$value) {
            if (is_int($key)) {
                if (!is_object($value)) {
                    throw new InvalidArgumentException($this->reflection, (string)$key);
                }
                $this->numericArguments[] = &$value;
            } else {
                $this->namedArguments[$key] = &$value;
            }
        }
    }
    private function prepareDataToTemplate(): void
    {
        $class = $this->reflection instanceof \ReflectionMethod
            ? $this->reflection->getDeclaringClass()
            : $this->reflection->getClosureScopeClass();

        $this->templateData = [
            Injector::TEMPLATE_METHOD => $this->reflection->getShortName(),
            Injector::TEMPLATE_CLASS => $class === null
                ? ''
                : $class->getName(),
            Injector::TEMPLATE_NAMESPACE => $class === null
                ? $this->reflection->getNamespaceName()
                : $class->getNamespaceName(),
        ];
    }
}
