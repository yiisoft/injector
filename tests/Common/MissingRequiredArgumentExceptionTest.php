<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common;

use Yiisoft\Injector\MissingRequiredArgumentException;
use ReflectionFunction;

class MissingRequiredArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = MissingRequiredArgumentException::class;

    public function testMessage(): void
    {
        $reflection = new ReflectionFunction('\\array_map');
        $exception = $this->createException($reflection, 'someParameter');

        $this->assertSame(
            'Missing required argument "someParameter" when calling "array_map".',
            $exception->getMessage(),
        );
    }
}
