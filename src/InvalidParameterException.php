<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Psr\Container\ContainerExceptionInterface;

class InvalidParameterException extends \InvalidArgumentException implements ContainerExceptionInterface
{
    public function __construct(string $name, string $functionName)
    {
        parent::__construct("Invalid parameter on key \"$name\" when calling \"$functionName\".", 0, null);
    }
}
