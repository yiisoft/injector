<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests;

class MissingRequiredArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = \Yiisoft\Injector\MissingRequiredArgumentException::class;

    public function testMessage(): void
    {
        $reflection = new \ReflectionFunction('\\array_map');
        $exception = $this->createException($reflection, 'someParameter');

        $this->assertSame(
            'Missing required argument "someParameter" when calling "array_map".',
            $exception->getMessage()
        );
    }
}
