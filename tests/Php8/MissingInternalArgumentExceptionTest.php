<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8;

class MissingInternalArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = \Yiisoft\Injector\MissingInternalArgumentException::class;
}
