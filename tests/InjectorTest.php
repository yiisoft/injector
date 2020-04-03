<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Yiisoft\Di\Container;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\InvalidArgumentException;
use Yiisoft\Injector\MissingRequiredArgumentException;
use Yiisoft\Injector\Tests\Support\ColorInterface;
use Yiisoft\Injector\Tests\Support\EngineInterface;
use Yiisoft\Injector\Tests\Support\EngineMarkTwo;
use Yiisoft\Injector\Tests\Support\EngineZIL130;
use Yiisoft\Injector\Tests\Support\EngineVAZ2101;
use Yiisoft\Injector\Tests\Support\LightEngine;
use Yiisoft\Injector\Tests\Support\MakeEmptyConstructor;
use Yiisoft\Injector\Tests\Support\MakeEngineCollector;
use Yiisoft\Injector\Tests\Support\MakeEngineMatherWithParam;
use Yiisoft\Injector\Tests\Support\MakeNoConstructor;
use Yiisoft\Injector\Tests\Support\MakePrivateConstructor;

class InjectorTest extends TestCase
{
    /**
     * Injector should be able to invoke closure.
     */
    public function testInvokeClosure(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $getEngineName = fn (EngineInterface $engine) => $engine->getName();

        $engineName = (new Injector($container))->invoke($getEngineName);

        $this->assertSame('Mark Two', $engineName);
    }

    /**
     * Injector should be able to invoke array callable.
     */
    public function testInvokeCallableArray(): void
    {
        $container = new Container([]);

        $object = new EngineVAZ2101();

        $engine = (new Injector($container))->invoke([$object, 'rust'], ['index' => 5.0]);

        $this->assertInstanceOf(EngineVAZ2101::class, $engine);
    }

    /**
     * Injector should be able to invoke static method.
     */
    public function testInvokeStatic(): void
    {
        $container = new Container([]);

        $result = (new Injector($container))->invoke([EngineVAZ2101::class, 'isWroomWroom']);

        $this->assertIsBool($result);
    }

    /**
     * Injector should be able to invoke static method.
     */
    public function testInvokeAnonymousClass(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);
        $class = new class() {
            public EngineInterface $engine;
            public function setEngine(EngineInterface $engine)
            {
                $this->engine = $engine;
            }
        };

        (new Injector($container))->invoke([$class, 'setEngine']);

        $this->assertInstanceOf(EngineInterface::class, $class->engine);
    }

    /**
     * Injector should be able to invoke method without arguments.
     */
    public function testInvokeWithoutArguments(): void
    {
        $container = new Container([]);

        $true = fn () => true;

        $result = (new Injector($container))->invoke($true);

        $this->assertTrue($result);
    }

    /**
     * Nullable arguments should be searched in container.
     */
    public function testWithNullableArgument(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNotNull($result);
    }

    /**
     * Nullable arguments not found in container should be passed as `null`.
     */
    public function testWithNullableArgumentAndEmptyContainer(): void
    {
        $container = new Container([]);

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNull($result);
    }

    /**
     * Nullable scalars should be set with `null` if not specified by name explicitly.
     */
    public function testWithNullableScalarArgument(): void
    {
        $container = new Container([]);

        $nullableInt = fn (?int $number) => $number;

        $result = (new Injector($container))->invoke($nullableInt);

        $this->assertNull($result);
    }

    /**
     * Optional scalar arguments should be set with default value if not specified by name explicitly.
     */
    public function testWithNullableOptionalArgument(): void
    {
        $container = new Container([]);

        $nullableInt = fn (?int $number = 6) => $number;

        $result = (new Injector($container))->invoke($nullableInt);

        $this->assertSame(6, $result);
    }

    /**
     * Optional arguments with `null` by default should be set with `null` if other value not specified in parameters
     * or container.
     */
    public function testWithNullableOptionalArgumentThatNull(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (EngineInterface $engine = null) => $engine;

        $result = (new Injector($container))->invoke($callable);

        $this->assertNotNull($result);
    }

    /**
     * An object for a typed argument can be specified in parameters without named key and without following the order.
     */
    public function testCustomDependency(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);
        $needleEngine = new EngineZIL130();

        $getEngineName = fn (EngineInterface $engine) => $engine->getName();

        $engineName = (new Injector($container))->invoke(
            $getEngineName,
            [new \stdClass(), $needleEngine, new DateTimeImmutable()]
        );

        $this->assertSame(EngineZIL130::NAME, $engineName);
    }

    /**
     * In this case, first argument will be set from parameters, and second argument from container.
     */
    public function testTwoEqualCustomArgumentsWithOneCustom(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $compareEngines = static function (EngineInterface $engine1, EngineInterface $engine2) {
            return $engine1->getPower() <=> $engine2->getPower();
        };
        $zilEngine = new EngineZIL130();

        $result = (new Injector($container))->invoke($compareEngines, [$zilEngine]);

        $this->assertSame(-1, $result);
    }

    /**
     * In this case, second argument will be set from parameters by name, and first argument from container.
     */
    public function testTwoEqualCustomArgumentsWithOneCustomNamedParameter(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $compareEngines = static function (EngineInterface $engine1, EngineInterface $engine2) {
            return $engine1->getPower() <=> $engine2->getPower();
        };
        $zilEngine = new EngineZIL130();

        $result = (new Injector($container))->invoke($compareEngines, ['engine2' => $zilEngine]);

        $this->assertSame(1, $result);
    }

    /**
     * Values for arguments are not matched by the greater similarity of parameter types and arguments, but simply pass
     * in order as is.
     */
    public function testExtendedArgumentsWithOneCustomNamedParameter2(): void
    {
        $container = new Container(
            [
                LightEngine::class => EngineVAZ2101::class,
            ]
        );

        $concatEngineNames = static function (EngineInterface $engine1, LightEngine $engine2) {
            return $engine1->getName() . $engine2->getName();
        };

        $result = (new Injector($container))->invoke($concatEngineNames, [
            new EngineMarkTwo(), // LightEngine, EngineInterface
            new EngineZIL130(), // EngineInterface
        ]);

        $this->assertSame(EngineMarkTwo::NAME . EngineVAZ2101::NAME, $result);
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

    /**
     * A values collection for a variadic argument can be passed as an array in a named parameter.
     */
    public function testAloneScalarVariadicArgumentAnsNamedParam(): void
    {
        $container = new Container([]);

        $callable = fn (...$var) => array_sum($var);

        $result = (new Injector($container))->invoke($callable, ['var' => [1, 2, 3]]);

        $this->assertSame(6, $result);
    }

    /**
     * If type of a variadic argument is a class and named parameter with values collection is not set then injector
     * will search for objects by class name among all unnamed parameters.
     */
    public function testVariadicArgumentUnnamedParams(): void
    {
        $container = new Container([\DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (\DateTimeInterface $dateTime, EngineInterface ...$engines) => count($engines);

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new \stdClass(), new EngineMarkTwo(), new \stdClass()]
        );

        $this->assertSame(3, $result);
    }

    /**
     * If calling method have an untyped variadic argument then all remaining unnamed parameters will be passed.
     */
    public function testVariadicMixedArgumentWithMixedParams(): void
    {
        $container = new Container([\DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (...$engines) => $engines;

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new EngineMarkTwo(), new \stdClass()]
        );

        $this->assertCount(4, $result);
    }

    /**
     * Any unnamed parameter can only be an object. Scalar, array, null and other values can only be named parameters.
     */
    public function testVariadicStringArgumentWithUnnamedStringsParams(): void
    {
        $container = new Container([\DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (string ...$engines) => $engines;

        $this->expectException(\Exception::class);

        (new Injector($container))->invoke($callable, ['str 1', 'str 2', 'str 3']);
    }

    /**
     * In the absence of other values to a nullable variadic argument `null` is not passed by default.
     */
    public function testNullableVariadicArgument(): void
    {
        $container = new Container([]);

        $callable = fn (?EngineInterface ...$engines) => $engines;

        $result = (new Injector($container))->invoke($callable, []);

        $this->assertSame([], $result);
    }

    /**
     * Parameters that were passed but were not used are appended to the call so they could be obtained
     * with func_get_args().
     */
    public function testAppendingUnusedParams(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (?EngineInterface $engine, $id = 'test') => func_num_args();

        $result = (new Injector($container))->invoke($callable, [new DateTimeImmutable(), new DateTimeImmutable()]);

        $this->assertSame(4, $result);
    }

    public function testWrongNamedParam(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $callable = fn (EngineInterface $engine) => $engine;

        $this->expectException(\Throwable::class);

        (new Injector($container))->invoke($callable, ['engine' => new DateTimeImmutable()]);
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

        $this->expectException(InvalidArgumentException::class);

        (new Injector($container))->invoke($getEngineName, ['test']);
    }

    /**
     * Constructor method not defined
     */
    public function testMakeWithoutConstructor(): void
    {
        $container = new Container([]);

        $object = (new Injector($container))->make(MakeNoConstructor::class);

        $this->assertInstanceOf(MakeNoConstructor::class, $object);
    }

    /**
     * Constructor without arguments
     */
    public function testMakeWithoutArguments(): void
    {
        $container = new Container([]);

        $object = (new Injector($container))->make(MakeEmptyConstructor::class);

        $this->assertInstanceOf(MakeEmptyConstructor::class, $object);
    }

    /**
     * Private constructor unavailable from Injector context
     */
    public function testMakeWithPrivateConstructor(): void
    {
        $container = new Container([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        (new Injector($container))->make(MakePrivateConstructor::class);
    }

    public function testMakeInvalidClass(): void
    {
        $container = new Container([]);

        $this->expectException(\ReflectionException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        (new Injector($container))->make(UndefinedClassThatShouldNotBeDefined::class);
    }

    public function testMakeInternalClass(): void
    {
        $container = new Container([]);
        $object = (new Injector($container))->make(DateTimeImmutable::class);
        $this->assertInstanceOf(DateTimeImmutable::class, $object);
    }

    public function testMakeAbstractClass(): void
    {
        $container = new Container([]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');
        (new Injector($container))->make(LightEngine::class);
    }

    public function testMakeInterface(): void
    {
        $container = new Container([]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');
        (new Injector($container))->make(EngineInterface::class);
    }

    public function testMakeWithVariadicFromContainer(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $object = (new Injector($container))->make(MakeEngineCollector::class, []);

        $this->assertSame(1, count($object->engines));
        $this->assertSame([$container->get(EngineInterface::class)], $object->engines);
    }

    public function testMakeWithVariadicFromArguments(): void
    {
        $container = new Container([]);

        $values = [new EngineMarkTwo(), new EngineVAZ2101()];
        $object = (new Injector($container))->make(MakeEngineCollector::class, $values);

        $this->assertSame($values, $object->engines);
    }

    public function testMakeWithCustomParam(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $object = (new Injector($container))
            ->make(MakeEngineMatherWithParam::class, [new EngineVAZ2101(), 'parameter' => 'power']);

        $this->assertNotSame($object->engine1, $object->engine2);
        $this->assertSame($object->parameter, 'power');
    }

    public function testMakeWithInvalidCustomParam(): void
    {
        $container = new Container([EngineInterface::class => EngineMarkTwo::class]);

        $this->expectException(\TypeError::class);

        (new Injector($container))
            ->make(MakeEngineMatherWithParam::class, ['parameter' => 100500]);
    }
}
