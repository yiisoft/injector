<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

final class MissingInternalArgumentException extends ArgumentException
{
    protected const EXCEPTION_MESSAGE = 'Can not determine default value of parameter "%s" when calling "%s"%s because'
    . ' it is PHP internal. Please specify argument explicitly.';
}
