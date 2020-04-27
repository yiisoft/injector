<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

final class InvalidArgumentException extends ArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Invalid argument on key "%s" when calling "%s"%s.';
}
