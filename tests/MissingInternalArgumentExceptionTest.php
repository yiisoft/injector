<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests;

class MissingInternalArgumentExceptionTest extends ArgumentExceptionTest
{
    protected const EXCEPTION_CLASS_NAME = \Yiisoft\Injector\MissingInternalArgumentException::class;

    public function testMessage(): void
    {
        $reflection = new \ReflectionFunction('\\array_map');
        $exception = $this->createException($reflection, 'someParameter');

        $this->assertSame(
            'Can not determine default value of parameter "someParameter" when calling "array_map" because it is PHP'
            . ' internal. Please specify argument explicitly.',
            $exception->getMessage()
        );
    }
}
