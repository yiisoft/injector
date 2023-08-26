<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common\Support;

final class Red implements ColorInterface
{
    public function getColor(): string
    {
        return 'red';
    }
}
