<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8\Support;

enum IntEnum: int
{
    case Foo = 1;
    case Bar = 2;
}
