<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common\Support;

class Invokeable
{
    public function __invoke(): int
    {
        return 42;
    }
}
