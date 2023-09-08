<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests;

class MissingRequiredArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = \Yiisoft\Injector\MissingRequiredArgumentException::class;
}
