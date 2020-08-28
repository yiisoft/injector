<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8;

class MissingRequiredArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = \Yiisoft\Injector\MissingRequiredArgumentException::class;
}
