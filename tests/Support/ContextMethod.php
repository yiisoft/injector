<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Support;

class ContextMethod
{
    private function privateMethod(): bool
    {
        return true;
    }
    protected function protectedMethod(): bool
    {
        return true;
    }
    private static function staticMethod(): string
    {
        return static::class;
    }
}
