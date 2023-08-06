<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8\Support;

enum StrEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}
