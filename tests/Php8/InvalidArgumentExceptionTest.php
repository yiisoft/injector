<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8;

class InvalidArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = \Yiisoft\Injector\InvalidArgumentException::class;
}
