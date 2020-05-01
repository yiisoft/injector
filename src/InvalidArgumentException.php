<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

final class InvalidArgumentException extends ArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Invalid argument "%s" when calling "%s"%s. Non-interface argument should be'
    . ' named explicitly when passed.';
}
