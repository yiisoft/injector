<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

final class InvalidArgumentException extends \InvalidArgumentException
{
    public function __construct(string $name, string $functionName)
    {
        parent::__construct("Invalid argument on key \"$name\" when calling \"$functionName\".", 0, null);
    }
}
