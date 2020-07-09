<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php7;

use Yiisoft\Injector\Injector;
use Yiisoft\Injector\MissingInternalArgumentException;
use Yiisoft\Injector\Tests\Common\BaseInjectorTest;

class InjectorTest extends BaseInjectorTest
{
    public function testMakeInternalClassWithOptionalMiddleArgumentSkipped(): void
    {
        $container = $this->getContainer();

        $this->expectException(MissingInternalArgumentException::class);
        $this->expectExceptionMessageMatches('/PHP internal/');

        (new Injector($container))->make(\SplFileObject::class, [
            'file_name' => __FILE__,
            // second parameter skipped
            // third parameter skipped
            'context' => null,
            'other-parameter' => true,
        ]);
    }
}
