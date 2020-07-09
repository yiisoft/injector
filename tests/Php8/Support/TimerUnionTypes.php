<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8\Support;

use DateTimeInterface;

class TimerUnionTypes
{
    private string|DateTimeInterface $time;
    public function __construct(string|DateTimeInterface $time)
    {
        $this->time = $time;
    }
    public function getTime(): string|DateTimeInterface
    {
        return $time;
    }
}
