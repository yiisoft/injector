<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common\Support;

class MakeEngineCollector
{
    public array $engines;

    public function __construct(EngineInterface ...$engines)
    {
        $this->engines = $engines;
    }
}
