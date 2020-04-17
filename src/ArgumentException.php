<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

abstract class ArgumentException extends \InvalidArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Something is wrong with argument "%s" when calling "%s".';

    public function __construct(\ReflectionFunctionAbstract $reflection, string $parameter)
    {
        $function = $reflection->getName();
        $class = $reflection->class ?? null;
        $method = $class !== null ? "{$class}::{$function}" : $function;

        parent::__construct(sprintf(static::EXCEPTION_MESSAGE, $parameter, $method));
    }
}
