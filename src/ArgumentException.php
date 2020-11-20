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
        $class = $reflection->class ?? null;

        if ($class === null) {
            $method = $function;
            if (substr($method, -9) === '{closure}') {
                $method = $this->getClosureSignature($reflection);
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

    private function getClosureSignature(\ReflectionFunctionAbstract $reflection): string
    {
        $closureParameters = [];
        $append = static function (bool $condition, string $postfix) use (&$parameterString): void {
            if ($condition) {
                $parameterString .= $postfix;
            }
        };
        foreach ($reflection->getParameters() as $parameter) {
            $parameterString = '';
            $type = $parameter->getType();
            /** @psalm-suppress UndefinedClass */
            if ($type instanceof ReflectionNamedType) {
                $append($parameter->allowsNull(), '?');
                $parameterString .= $type->getName() . ' ';
            } elseif ($type instanceof ReflectionUnionType) {
                $parameterString .= implode('|', array_map(
                    fn (ReflectionNamedType $r) => $r->getName(),
                    $type->getTypes()
                )) . ' ';
            }
            $append($parameter->isPassedByReference(), '&');
            $append($parameter->isVariadic(), '...');
            $parameterString .= '$' . $parameter->name;
            if ($parameter->isDefaultValueAvailable()) {
                $parameterString .= ' = ' . var_export($parameter->getDefaultValue(), true);
            }
            $closureParameters[] = $parameterString;
        }
        return 'function (' . implode(', ', $closureParameters) . ')';
    }
}
