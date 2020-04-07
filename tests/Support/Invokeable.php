<?php


namespace Yiisoft\Injector\Tests\Support;

class Invokeable
{
    public function __invoke(): int
    {
        return 42;
    }
}
