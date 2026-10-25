<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php86;

use Yiisoft\Injector\Injector;
use Yiisoft\Injector\InvalidArgumentException;
use Yiisoft\Injector\MissingRequiredArgumentException;
use Yiisoft\Injector\Tests\Common\BaseInjectorTest;
use Yiisoft\Injector\Tests\Common\Support\EngineInterface;
use Yiisoft\Injector\Tests\Common\Support\EngineMarkTwo;

final class InjectorTest extends BaseInjectorTest
{
    public function testInvokePartialFunctionApplicationWithPlaceholder(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);
        $closure = static fn (EngineInterface $engine, string $unit): string => $engine->getName() . ' ' . $unit;
        $callable = $closure(?, 'km/h');

        $result = new Injector($container)->invoke($callable);

        $this->assertSame('Mark Two km/h', $result);
    }

    public function testInvokePartialFunctionApplicationWithVariadicPlaceholder(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);
        $closure = static fn (EngineInterface $engine, string $unit): string => $engine->getName() . ' ' . $unit;
        $callable = $closure(unit: 'mph', ...);

        $result = new Injector($container)->invoke($callable);

        $this->assertSame('Mark Two mph', $result);
    }

    public function testInvokePartialFunctionApplicationWithArguments(): void
    {
        $container = $this->getContainer();
        $closure = static fn (EngineInterface $engine, string $unit): string => $engine->getName() . ' ' . $unit;
        $callable = $closure(?, ?);

        $result = new Injector($container)->invoke($callable, [new EngineMarkTwo(), 'unit' => 'm/s']);

        $this->assertSame('Mark Two m/s', $result);
    }

    public function testInvokePartialFunctionApplicationWithMissingArgument(): void
    {
        $container = $this->getContainer();
        $closure = static fn (EngineInterface $engine, string $unit): string => $engine->getName() . ' ' . $unit;
        $callable = $closure(new EngineMarkTwo(), ?);

        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);
        $this->expectExceptionMessage('Missing required argument "unit" when calling "partial application (string $unit)"');
        $injector->invoke($callable);
    }

    public function testInvokePartialMethodApplicationWithMissingArgument(): void
    {
        $container = $this->getContainer();
        $object = new class {
            public function run(float $index): void {}
        };
        $callable = $object->run(?);

        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);
        $this->expectExceptionMessage('Missing required argument "index" when calling "partial application (float $index)"');
        $injector->invoke($callable);
    }

    public function testInvokePartialFunctionApplicationWithNamedScalarArgument(): void
    {
        $container = $this->getContainer();
        $callable = (static fn (int $a): int => $a)(?);

        $result = new Injector($container)->invoke($callable, ['a' => 5]);

        $this->assertSame(5, $result);
    }

    public function testInvokePartialFunctionApplicationWithUnnamedScalarArgument(): void
    {
        $container = $this->getContainer();
        $callable = (static fn (int $a): int => $a)(?);

        $injector = new Injector($container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid argument "0" when calling "partial application (int $a)"');
        $injector->invoke($callable, [5]);
    }
}
