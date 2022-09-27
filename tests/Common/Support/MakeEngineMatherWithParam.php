<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common\Support;

class MakeEngineMatherWithParam
{
    public function __construct(public string $parameter, public EngineInterface $engine1, public EngineInterface $engine2)
    {
    }
}
