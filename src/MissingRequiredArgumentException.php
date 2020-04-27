<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

final class MissingRequiredArgumentException extends ArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Missing required argument "%s" when calling "%s"%s.';
}
