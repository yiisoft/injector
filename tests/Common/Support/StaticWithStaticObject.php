<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common\Support;

final class StaticWithStaticObject
{
    public static bool $wasCalled = false;

    public static function foo(): string
    {
        self::$wasCalled = true;
        return 'bar';
    }
}
