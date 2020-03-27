<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

class MissingRequiredArgumentException extends \InvalidArgumentException
{
    public function __construct(string $name, string $functionName)
    {
        parent::__construct("Missing required argument \"$name\" when calling \"$functionName\".", 0, null);
    }
}
