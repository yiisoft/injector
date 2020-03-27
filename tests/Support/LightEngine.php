<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Support;

abstract class LightEngine implements EngineInterface
{
    protected int $power;

    public function getPower(): int
    {
        return $this->power;
    }

    public static function isWroomWroom(): bool
    {
        return true;
    }
}
