<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Yiisoft\Di\Container;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\InvalidParameterException;
use Yiisoft\Injector\MissingRequiredArgumentException;
use Yiisoft\Injector\Tests\Support\ColorInterface;
use Yiisoft\Injector\Tests\Support\EngineInterface;
use Yiisoft\Injector\Tests\Support\EngineMarkTwo;
use Yiisoft\Injector\Tests\Support\EngineZIL130;
use Yiisoft\Injector\Tests\Support\EngineVAZ2101;
use Yiisoft\Injector\Tests\Support\LightEngine;

class InjectorTest extends TestCase
{
    public function testInvokeClosure(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $getEngineName = static function (EngineInterface $engine) {
            return $engine->getName();
        };

        $engineName = (new Injector($container))->invoke($getEngineName);

        $this->assertSame('Mark Two', $engineName);
    }

    public function testInvokeCallableArray(): void
    {
        $container = new Container([]);

        $object = new EngineVAZ2101();

        $engineName = (new Injector($container))->invoke([$object, 'rust'], ['index' => 5.0]);

        $this->assertInstanceOf(EngineVAZ2101::class, $engineName);
    }

    public function testInvokeStatic(): void
    {
        $container = new Container([]);

        $engineName = (new Injector($container))->invoke([EngineVAZ2101::class, 'isWroomWroom']);

        $this->assertIsBool($engineName);
    }

    public function testInvokeWithoutArguments(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $true = fn () => true;

        $result = (new Injector($container))->invoke($true);

        $this->assertTrue($result);
    }

    public function testWithNullableArgument(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNotNull($result);
    }

    public function testWithNullableArgumentAndEmptyContainer(): void
    {
        $container = new Container([]);

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNull($result);
    }

    public function testWithNullableScalarArgument(): void
    {
        $container = new Container([]);

        $nullableInt = fn (?int $number) => $number;

        $result = (new Injector($container))->invoke($nullableInt);

        $this->assertNull($result);
    }

    public function testWithNullableOptionalArgument(): void
    {
        $container = new Container([]);

        $nullableInt = fn (?int $number = 6) => $number;

        $result = (new Injector($container))->invoke($nullableInt);

        $this->assertSame(6, $result);
    }

    public function testWithNullableOptionalArgumentThatNull(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (EngineInterface $engine = null) => $engine;

        $result = (new Injector($container))->invoke($callable);

        $this->assertNotNull($result);
    }

    public function testCustomDependency(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);
        $needleEngine = new EngineZIL130();

        $getEngineName = fn (EngineInterface $engine) => $engine->getName();

        $engineName = (new Injector($container))->invoke($getEngineName, [$needleEngine]);

        $this->assertSame(EngineZIL130::NAME, $engineName);
    }

    public function testTwoEqualCustomArgumentsWithOneCustom(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $compareEngines = function (EngineInterface $engine1, EngineInterface $engine2) {
            return $engine1->getPower() <=> $engine2->getPower();
        };
        $zilEngine = new EngineZIL130();

        $engineName = (new Injector($container))->invoke($compareEngines, [$zilEngine]);

        $this->assertSame(-1, $engineName);
    }

    public function testTwoEqualCustomArgumentsWithOneCustomNamedParameter(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $compareEngines = function (EngineInterface $engine1, EngineInterface $engine2) {
            return $engine1->getPower() <=> $engine2->getPower();
        };
        $zilEngine = new EngineZIL130();

        $engineName = (new Injector($container))->invoke($compareEngines, ['engine1' => $zilEngine]);

        $this->assertSame(-1, $engineName);
    }

    public function testTwoEqualCustomArgumentsWithOneCustomNamedParameter2(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $compareEngines = function (EngineInterface $engine1, EngineInterface $engine2) {
            return $engine1->getPower() <=> $engine2->getPower();
        };
        $zilEngine = new EngineZIL130();

        $engineName = (new Injector($container))->invoke($compareEngines, ['engine2' => $zilEngine]);

        $this->assertSame(1, $engineName);
    }

    public function testExtendedArgumentsWithOneCustomNamedParameter2(): void
    {
        $container = new Container(
            [
                EngineInterface::class => EngineZIL130::class,
                LightEngine::class => EngineVAZ2101::class,
            ]
        );

        $compareEngines = function (EngineInterface $engine1, LightEngine $engine2) {
            return $engine1->getName() . $engine2->getName();
        };
        $zilEngine = new EngineMarkTwo();

        $engineName = (new Injector($container))->invoke($compareEngines, [$zilEngine]);

        $this->assertSame(EngineMarkTwo::NAME . EngineVAZ2101::NAME, $engineName);
    }

    public function testMissingRequiredTypedParameter(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $getEngineName = static function (EngineInterface $engine, string $two) {
            return $engine->getName();
        };

        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);
        $injector->invoke($getEngineName);
    }

    public function testMissingRequiredNotTypedParameter(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $getEngineName = static function (EngineInterface $engine, $two) {
            return $engine->getName();
        };
        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);

        $injector->invoke($getEngineName);
    }

    public function testNotFoundException(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $getEngineName = static function (EngineInterface $engine, ColorInterface $color) {
            return $engine->getName();
        };

        $injector = new Injector($container);

        $this->expectException(NotFoundExceptionInterface::class);
        $injector->invoke($getEngineName);
    }

    public function testAloneScalarVariadicArgumentAnsNamedParam(): void
    {
        $container = new Container([]);

        $callable = fn (...$var) => array_sum($var);

        $result = (new Injector($container))->invoke($callable, ['var' => [1, 2, 3]]);

        $this->assertSame(6, $result);
    }

    public function testScalarVariadicArgumentAnsNamedParam(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (EngineInterface $engine, int ...$var) => array_sum($var);

        $result = (new Injector($container))->invoke($callable, ['var' => [1, 2, 3]]);

        $this->assertSame(6, $result);
    }

    public function testVariadicArgumentUnnamedParams(): void
    {
        $container = new Container([\DateTimeInterface::class => new \DateTimeImmutable()]);

        $callable = fn (\DateTimeInterface $dateTime, EngineInterface ...$engines) => count($engines);

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new EngineMarkTwo()]
        );

        $this->assertSame(3, $result);
    }

    public function testVariadicArgumentUnnamedParamsWithIncorrectItem(): void
    {
        $container = new Container([\DateTimeInterface::class => new \DateTimeImmutable()]);

        $callable = fn (\DateTimeInterface $dateTime, EngineInterface ...$engines) => count($engines);

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new EngineMarkTwo(), new \stdClass()]
        );

        // stdClass should be ignored
        $this->assertSame(3, $result);
    }

    public function testVariadicMixedArgumentWithMixedParams(): void
    {
        $container = new Container([\DateTimeInterface::class => new \DateTimeImmutable()]);

        $callable = fn (...$engines) => $engines;

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new EngineMarkTwo(), new \stdClass()]
        );

        $this->assertSame(4, count($result));
    }

    public function testVariadicStringArgumentWithUnnamedStringsParams(): void
    {
        $container = new Container([\DateTimeInterface::class => new \DateTimeImmutable()]);

        $callable = fn (string ...$engines) => $engines;

        $this->expectException(\Exception::class);

        $result = (new Injector($container))->invoke($callable, ['str 1', 'str 2', 'str 3']);
    }

    public function testNullableVariadicArgument(): void
    {
        $container = new Container([]);

        $callable = fn (?EngineInterface ...$engines) => $engines;

        $result = (new Injector($container))->invoke($callable, []);

        $this->assertSame([], $result);
    }

    public function testAppendingUnusedParams(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (?EngineInterface $engine, $id = 'test') => func_num_args();

        $result = (new Injector($container))->invoke($callable, [new \DateTimeImmutable(), new \DateTimeImmutable()]);

        $this->assertSame(4, $result);
    }

    public function testWrongNamedParam(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (EngineInterface $engine) => $engine;

        $this->expectException(\Throwable::class);

        $result = (new Injector($container))->invoke($callable, ['engine' => new \DateTimeImmutable()]);
    }

    public function testArrayArgumentWithUnnamedType(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (array $arg) => $arg;

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [['test']]);
    }

    public function testCallableArgumentWithUnnamedType(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (callable $arg) => $arg();

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [fn () => true]);
    }

    public function testIterableArgumentWithUnnamedType(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (iterable $arg) => $arg;

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [new \SplStack()]);
    }

    public function testUnnamedScalarParam(): void
    {
        $container = new Container([]);

        $getEngineName = fn () => 42;

        $this->expectException(InvalidParameterException::class);

        (new Injector($container))->invoke($getEngineName, ['test']);
    }
}
