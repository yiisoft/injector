<?php
namespace Yiisoft\Injector\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use yii\di\Container;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\MissingRequiredArgument;
use Yiisoft\Injector\Tests\Support\ColorInterface;
use Yiisoft\Injector\Tests\Support\EngineInterface;
use Yiisoft\Injector\Tests\Support\EngineMarkTwo;

/**
 * InjectorTest contains tests for \yii\di\Injector
 */
class InjectorTest extends TestCase
{
    public function testInvoke(): void
    {
        $container = new Container([
            EngineInterface::class => EngineMarkTwo::class,
        ]);

        $getEngineName = static function (EngineInterface $engine) {
            return $engine->getName();
        };

        $engineName = (new Injector($container))->invoke($getEngineName);

        $this->assertSame('Mark Two', $engineName);
    }

    public function testMissingRequiredParameter(): void
    {
        $container = new Container([
            EngineInterface::class => EngineMarkTwo::class,
        ]);

        $getEngineName = static function (EngineInterface $engine, $two) {
            return $engine->getName();
        };

        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgument::class);
        $injector->invoke($getEngineName);
    }

    public function testNotFoundException(): void
    {
        $container = new Container([
            EngineInterface::class => EngineMarkTwo::class,
        ]);

        $getEngineName = static function (EngineInterface $engine, ColorInterface $color) {
            return $engine->getName();
        };

        $injector = new Injector($container);

        $this->expectException(NotFoundExceptionInterface::class);
        $injector->invoke($getEngineName);
    }
}
