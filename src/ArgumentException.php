<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

use InvalidArgumentException;

use function array_map;
use function get_class;
use function implode;
use function is_object;
use function method_exists;
use function sprintf;
use function var_export;

abstract class ArgumentException extends InvalidArgumentException
{
    /**
     * @var string
     */
    protected const EXCEPTION_MESSAGE = 'Something is wrong with argument "%s" when calling "%s"%s.';

    public function __construct(ReflectionFunctionAbstract $reflection, string $parameter)
    {
        $function = $reflection->getName();
        /** @var class-string|null $class */
        $class = $reflection->class ?? null;

        if ($class === null) {
            $method = $function;
            if (strpos($method, '{closure:pfa:') === 0) {
                $method = $this->renderPartialApplicationSignature($reflection);
            } elseif (strpos($method, '{closure') !== false) {
                $method = $this->renderClosureSignature($reflection);
            }
        } else {
            $method = "{$class}::{$function}";
        }

        $fileName = $reflection->getFileName();
        $line = $reflection->getStartLine();

        $fileAndLine = '';
        if (!empty($fileName)) {
            $fileAndLine = " in \"$fileName\" at line $line";
        }

        parent::__construct(sprintf(static::EXCEPTION_MESSAGE, $parameter, $method, $fileAndLine));
    }

    private function renderClosureSignature(ReflectionFunctionAbstract $reflection): string
    {
        // `ReflectionFunctionAbstract::isStatic()` is available since PHP 8.0 only.
        // @phpstan-ignore function.alreadyNarrowedType
        $static = method_exists($reflection, 'isStatic') && $reflection->isStatic() ? 'static ' : '';
        return $static . 'function (' . $this->renderParameters($reflection) . ')';
    }

    /**
     * Renders signature of a closure created by partial function application (PHP 8.6+), e.g. `foo(?, 42)`.
     */
    private function renderPartialApplicationSignature(ReflectionFunctionAbstract $reflection): string
    {
        return 'partial application (' . $this->renderParameters($reflection) . ')';
    }

    private function renderParameters(ReflectionFunctionAbstract $reflection): string
    {
        $closureParameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameterString = sprintf(
                '%s%s%s$%s',
                // type
                $this->renderParameterType($parameter),
                // reference
                $parameter->isPassedByReference() ? '&' : '',
                // variadic
                $parameter->isVariadic() ? '...' : '',
                $parameter->getName(),
            );
            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
                $parameterString .= ' = ';
                if (is_object($default)) {
                    $parameterString .= 'new ' . get_class($default) . '(...)';
                } elseif ($parameter->isDefaultValueConstant()) {
                    $parameterString .= $parameter->getDefaultValueConstantName();
                } else {
                    $parameterString .= var_export($default, true);
                }
            }
            $closureParameters[] = $parameterString;
        }

        return implode(', ', $closureParameters);
    }

    private function renderParameterType(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType) {
            return sprintf(
                '%s%s ',
                $parameter->allowsNull() ? '?' : '',
                $type->getName(),
            );
        }
        if ($type instanceof ReflectionUnionType) {
            /** @var ReflectionNamedType[] $types */
            $types = $type->getTypes();
            return implode('|', array_map(
                static fn(ReflectionNamedType $r) => $r->getName(),
                $types,
            )) . ' ';
        }
        if ($type instanceof ReflectionIntersectionType) {
            /** @var ReflectionNamedType[] $types */
            $types = $type->getTypes();
            return implode('&', array_map(
                static fn(ReflectionNamedType $r) => $r->getName(),
                $types,
            )) . ' ';
        }
        return '';
    }
}
