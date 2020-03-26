<?php

namespace Yiisoft\Injector;

use Psr\Container\ContainerExceptionInterface;

class MissingRequiredArgumentException extends \InvalidArgumentException implements ContainerExceptionInterface
{
    public function __construct(string $name, string $functionName)
    {
        parent::__construct("Missing required argument \"$name\" when calling \"$functionName\".", 0, null);
    }
}
