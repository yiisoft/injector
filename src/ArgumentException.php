<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

abstract class ArgumentException extends \InvalidArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Something is wrong with argument "%s" when calling "%s"%s.';

    public function __construct(\ReflectionFunctionAbstract $reflection, string $parameter)
    {
        $function = $reflection->getName();
        $class = $reflection->class ?? null;

        if ($class === null) {
            $method = $function;
            if ($method === '{closure}') {
                $method = $this->getClosureSignature($reflection);
            }
        } else {
            $method = "{$class}::{$function}";
        }

        $fileName = $reflection->getFileName();
        $line = $reflection->getStartLine();

        $fileAndLine = '';
        if (!empty($fileName) && !empty($line)) {
            $fileAndLine = " in \"$fileName\" at line \"$line\"";
        }

        parent::__construct(sprintf(static::EXCEPTION_MESSAGE, $parameter, $method, $fileAndLine));
    }

    private function getClosureSignature(\ReflectionFunctionAbstract $reflection): string
    {
        $result = 'function (';
        $closureParameters = [];
        foreach ($reflection->getParameters() as $parameter) {
            $parameterString = '';
            if ($parameter->isArray()) {
                $parameterString .= 'array ';
            } elseif ($parameter->getClass()) {
                $parameterString .= $parameter->getClass()->name . ' ';
            }
            if ($parameter->isPassedByReference()) {
                $parameterString .= '&';
            }
            $parameterString .= '$' . $parameter->name;
            if ($parameter->isOptional()) {
                $parameterString .= ' = ' . var_export($parameter->getDefaultValue(), true);
            }
            $closureParameters[] = $parameterString;
        }
        $result .= implode(', ', $closureParameters);
        $result .= ')';
        return $result;
    }
}
