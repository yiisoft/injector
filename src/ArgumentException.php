<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use ReflectionNamedType;
use ReflectionUnionType;

abstract class ArgumentException extends \InvalidArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Something is wrong with argument "%s" when calling "%s"%s.';

    public function __construct(\ReflectionFunctionAbstract $reflection, string $parameter)
    {
        $function = $reflection->getName();
        /** @psalm-var class-string|null $class */
        $class = $reflection->class ?? null;

        if ($class === null) {
            $method = $function;
            if (substr($method, -9) === '{closure}') {
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

        parent::__construct(sprintf((string)static::EXCEPTION_MESSAGE, $parameter, $method, $fileAndLine));
    }

    private function renderClosureSignature(\ReflectionFunctionAbstract $reflection): string
    {
        $closureParameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameterString = \sprintf(
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
                if (\is_object($default)) {
                    $parameterString .= 'new ' . \get_class($default) . '(...)';
                } elseif ($parameter->isDefaultValueConstant()) {
                    $parameterString .= $parameter->getDefaultValueConstantName();
                } else {
                    $parameterString .= \var_export($default, true);
                }
            }
            $closureParameters[] = \ltrim($parameterString);
        }

        $static = \method_exists($reflection, 'isStatic') && $reflection->isStatic() ? 'static ' : '';
        return $static . 'function (' . implode(', ', $closureParameters) . ')';
    }

    private function renderParameterType(\ReflectionParameter $parameter)
    {
        /** @var ReflectionNamedType|ReflectionUnionType|null $type */
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType) {
            return sprintf(
                '%s%s ',
                $parameter->allowsNull() ? '?' : '',
                $type->getName()
            );
        }
        if ($type instanceof ReflectionUnionType) {
            /** @var ReflectionNamedType[] $types */
            $types = $type->getTypes();
            return \implode('|', \array_map(
                static fn (ReflectionNamedType $r) => $r->getName(),
                $types
            )) . ' ';
        }
        if ($type instanceof \ReflectionIntersectionType) {
            /** @var ReflectionNamedType[] $types */
            $types = $type->getTypes();
            return \implode('&', \array_map(
                static fn (ReflectionNamedType $r) => $r->getName(),
                $types
            )) . ' ';
        }
        return '';
    }
}
