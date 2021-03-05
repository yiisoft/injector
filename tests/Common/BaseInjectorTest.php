<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Yiisoft\Test\Support\Container\SimpleContainer;

abstract class BaseInjectorTest extends TestCase
{
    protected function getContainer(array $definitions = []): ContainerInterface
    {
        return new SimpleContainer($definitions);
    }
}
