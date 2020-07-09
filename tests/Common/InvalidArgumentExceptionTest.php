<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common;

class InvalidArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = \Yiisoft\Injector\InvalidArgumentException::class;

    public function testMessage(): void
    {
        $reflection = new \ReflectionFunction('\\array_map');
        $exception = $this->createException($reflection, 'someParameter');

        $this->assertSame(
            'Invalid argument "someParameter" when calling "array_map". Non-interface argument should be named'
            . ' explicitly when passed.',
            $exception->getMessage()
        );
    }
}
