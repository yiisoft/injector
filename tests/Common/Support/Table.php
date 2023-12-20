<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common\Support;

final class Table
{
    public ?ColorInterface $color;

    public function __construct(?ColorInterface $color = null)
    {
        $this->color = $color;
    }
}
