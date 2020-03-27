<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Support;

class EngineVAZ2101 extends LightEngine
{
    public const NAME = 'VAZ 2101';

    protected int $power = 59;

    public function getName(): string
    {
        return static::NAME;
    }

    public function rust(float $index): self
    {
        $this->power = (int)ceil($this->power / $index);
        return $this;
    }
}
