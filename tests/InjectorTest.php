<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\InvalidArgumentException;
use Yiisoft\Injector\MissingInternalArgumentException;
use Yiisoft\Injector\MissingRequiredArgumentException;
use Yiisoft\Injector\Tests\Support\ColorInterface;
use Yiisoft\Injector\Tests\Support\EngineInterface;
use Yiisoft\Injector\Tests\Support\EngineMarkTwo;
use Yiisoft\Injector\Tests\Support\EngineZIL130;
use Yiisoft\Injector\Tests\Support\EngineVAZ2101;
use Yiisoft\Injector\Tests\Support\Invokeable;
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
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $getEngineName = fn (EngineInterface $engine) => $engine->getName();

        $engineName = (new Injector($container))->invoke($getEngineName);

        $this->assertSame('Mark Two', $engineName);
    }

    /**
     * Injector should be able to invoke array callable.
     */
    public function testInvokeCallableArray(): void
    {
        $container = $this->getContainer();

        $object = new EngineVAZ2101();

        $engine = (new Injector($container))->invoke([$object, 'rust'], ['index' => 5.0]);

        $this->assertInstanceOf(EngineVAZ2101::class, $engine);
    }

    /**
     * Injector should be able to invoke static method.
     */
    public function testInvokeStatic(): void
    {
        $container = $this->getContainer();

        $result = (new Injector($container))->invoke([EngineVAZ2101::class, 'isWroomWroom']);

        $this->assertIsBool($result);
    }

    /**
     * Injector should be able to invoke static method.
     */
    public function testInvokeAnonymousClass(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);
        $class = new class() {
            public EngineInterface $engine;
            public function setEngine(EngineInterface $engine): void
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
        $container = $this->getContainer();

        $true = fn () => true;

        $result = (new Injector($container))->invoke($true);

        $this->assertTrue($result);
    }

    /**
     * Nullable arguments should be searched in container.
     */
    public function testWithNullableArgument(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNotNull($result);
    }

    /**
     * Nullable arguments not found in container should be passed as `null`.
     */
    public function testWithNullableArgumentAndEmptyContainer(): void
    {
        $container = $this->getContainer();

        $nullable = fn (?EngineInterface $engine) => $engine;

        $result = (new Injector($container))->invoke($nullable);

        $this->assertNull($result);
    }

    /**
     * Nullable scalars should be set with `null` if not specified by name explicitly.
     */
    public function testWithNullableScalarArgument(): void
    {
        $container = $this->getContainer();

        $nullableInt = fn (?int $number) => $number;

        $result = (new Injector($container))->invoke($nullableInt);

        $this->assertNull($result);
    }

    /**
     * Optional scalar arguments should be set with default value if not specified by name explicitly.
     */
    public function testWithNullableOptionalArgument(): void
    {
        $container = $this->getContainer();

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
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (EngineInterface $engine = null) => $engine;

        $result = (new Injector($container))->invoke($callable);

        $this->assertNotNull($result);
    }

    /**
     * An object for a typed argument can be specified in parameters without named key and without following the order.
     */
    public function testCustomDependency(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);
        $needleEngine = new EngineZIL130();

        $getEngineName = fn (EngineInterface $engine) => $engine->getName();

        $engineName = (new Injector($container))->invoke(
            $getEngineName,
            [new stdClass(), $needleEngine, new DateTimeImmutable()]
        );

        $this->assertSame(EngineZIL130::NAME, $engineName);
    }

    /**
     * In this case, first argument will be set from parameters, and second argument from container.
     */
    public function testTwoEqualCustomArgumentsWithOneCustom(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

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
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

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
        $container = $this->getContainer([LightEngine::class => new EngineVAZ2101()]);

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
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $getEngineName = static function (EngineInterface $engine, string $two) {
            return $engine->getName();
        };

        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);
        $injector->invoke($getEngineName);
    }

    public function testMissingRequiredNotTypedParameter(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $getEngineName = static function (EngineInterface $engine, $two) {
            return $engine->getName();
        };
        $injector = new Injector($container);

        $this->expectException(MissingRequiredArgumentException::class);

        $injector->invoke($getEngineName);
    }

    public function testNotFoundException(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

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
        $container = $this->getContainer();

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
        $container = $this->getContainer([DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (DateTimeInterface $dateTime, EngineInterface ...$engines) => count($engines);

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new stdClass(), new EngineMarkTwo(), new stdClass()]
        );

        $this->assertSame(3, $result);
    }

    /**
     * If calling method have an untyped variadic argument then all remaining unnamed parameters will be passed.
     */
    public function testVariadicMixedArgumentWithMixedParams(): void
    {
        $container = $this->getContainer([DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (...$engines) => $engines;

        $result = (new Injector($container))->invoke(
            $callable,
            [new EngineZIL130(), new EngineVAZ2101(), new EngineMarkTwo(), new stdClass()]
        );

        $this->assertCount(4, $result);
    }

    /**
     * Any unnamed parameter can only be an object. Scalar, array, null and other values can only be named parameters.
     */
    public function testVariadicStringArgumentWithUnnamedStringsParams(): void
    {
        $container = $this->getContainer([DateTimeInterface::class => new DateTimeImmutable()]);

        $callable = fn (string ...$engines) => $engines;

        $this->expectException(\Exception::class);

        (new Injector($container))->invoke($callable, ['str 1', 'str 2', 'str 3']);
    }

    /**
     * In the absence of other values to a nullable variadic argument `null` is not passed by default.
     */
    public function testNullableVariadicArgument(): void
    {
        $container = $this->getContainer();

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
        $container = $this->getContainer();

        $callable = fn (?EngineInterface $engine, $id = 'test') => func_num_args();

        $result = (new Injector($container))->invoke($callable, [
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            new EngineMarkTwo()
        ]);

        $this->assertSame(4, $result);
    }

    /**
     * Object type may be passed as unnamed parameter
     */
    public function testInvokeWithObjectType(): void
    {
        $container = $this->getContainer();
        $callable = fn (object $object) => get_class($object);

        $result = (new Injector($container))->invoke($callable, [new DateTimeImmutable()]);

        $this->assertSame(DateTimeImmutable::class, $result);
    }

    /**
     * Required `object` type should not be requested from the container
     */
    public function testInvokeWithRequiredObjectTypeWithoutInstance(): void
    {
        $container = $this->getContainer();
        $callable = fn (object $object) => get_class($object);

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable);
    }

    /**
     * Arguments passed by reference
     */
    public function testInvokeReferencedArguments(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);
        $foo = 1;
        $bar = new stdClass();
        $baz = null;
        $callable = static function (
            int &$foo,
            object &$bar,
            &$baz,
            ?ColorInterface &$nullable,
            EngineInterface &$object, // from container
            DateTimeInterface &...$dates // collect all unnamed DateTimeInterface objects
        ) {
            $return = func_get_args();
            $bar = new DateTimeImmutable();
            $baz = false;
            $foo = count($dates);
            return $return;
        };
        $result = (new Injector($container))
            ->invoke($callable, [
                new DateTimeImmutable(),
                new DateTime(),
                new DateTime(),
                'foo' => &$foo,
                'bar' => $bar,
                'baz' => &$baz,
            ]);

        // passed
        $this->assertSame(1, $result[0]);
        $this->assertInstanceOf(stdClass::class, $result[1]);
        $this->assertNull($result[2]);
        $this->assertNull($result[3]);
        $this->assertInstanceOf(EngineMarkTwo::class, $result[4]);
        // transformed
        $this->assertSame(3, $foo); // count of DateTimeInterface objects
        $this->assertInstanceOf(stdClass::class, $bar);
        $this->assertFalse($baz);
    }

    public function testInvokeReferencedArgumentNamedVariadic(): void
    {
        $container = $this->getContainer();

        $callable = static function (DateTimeInterface &...$dates) {
            $dates[0] = false;
            $dates[1] = false;
            return count($dates);
        };
        $foo = new DateTimeImmutable();
        $bar = new DateTimeImmutable();
        $result = (new Injector($container))
            ->invoke($callable, [
                $foo,
                &$bar,
                new DateTime(),
            ]);

        $this->assertSame(3, $result);
        $this->assertInstanceOf(DateTimeImmutable::class, $foo);
        $this->assertFalse($bar);
    }

    /**
     * If argument passed by reference but it is not supported by function
     */
    public function testInvokeReferencedArgument(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);
        $foo = 1;
        $callable = fn (int $foo) => ++$foo;
        $result = (new Injector($container))->invoke($callable, ['foo' => &$foo]);

        // $foo has been not changed
        $this->assertSame(1, $foo);
        $this->assertSame(2, $result);
    }

    public function testWrongNamedParam(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (EngineInterface $engine) => $engine;

        $this->expectException(\Throwable::class);

        (new Injector($container))->invoke($callable, ['engine' => new DateTimeImmutable()]);
    }

    public function testArrayArgumentWithUnnamedType(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (array $arg) => $arg;

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [['test']]);
    }

    public function testCallableArgumentWithUnnamedType(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (callable $arg) => $arg();

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [fn () => true]);
    }

    public function testIterableArgumentWithUnnamedType(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $callable = fn (iterable $arg) => $arg;

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->invoke($callable, [new \SplStack()]);
    }

    public function testUnnamedScalarParam(): void
    {
        $container = $this->getContainer();

        $getEngineName = fn () => 42;

        $this->expectException(InvalidArgumentException::class);

        (new Injector($container))->invoke($getEngineName, ['test']);
    }

    public function testInvokeable(): void
    {
        $container = $this->getContainer();
        $result = (new Injector($container))->invoke(new Invokeable());
        $this->assertSame(42, $result);
    }

    /**
     * Constructor method not defined
     */
    public function testMakeWithoutConstructor(): void
    {
        $container = $this->getContainer();

        $object = (new Injector($container))->make(MakeNoConstructor::class);

        $this->assertInstanceOf(MakeNoConstructor::class, $object);
    }

    /**
     * Constructor without arguments
     */
    public function testMakeWithoutArguments(): void
    {
        $container = $this->getContainer();

        $object = (new Injector($container))->make(MakeEmptyConstructor::class);

        $this->assertInstanceOf(MakeEmptyConstructor::class, $object);
    }

    /**
     * Private constructor unavailable from Injector context
     */
    public function testMakeWithPrivateConstructor(): void
    {
        $container = $this->getContainer();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        (new Injector($container))->make(MakePrivateConstructor::class);
    }

    public function testMakeInvalidClass(): void
    {
        $container = $this->getContainer();

        $this->expectException(\ReflectionException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        (new Injector($container))->make(UndefinedClassThatShouldNotBeDefined::class);
    }

    public function testMakeInternalClass(): void
    {
        $container = $this->getContainer();
        $object = (new Injector($container))->make(DateTimeImmutable::class);
        $this->assertInstanceOf(DateTimeImmutable::class, $object);
    }

    public function testMakeInternalClassWithOptionalMiddleArgumentSkipped(): void
    {
        $container = $this->getContainer();

        $this->expectException(MissingInternalArgumentException::class);
        $this->expectExceptionMessageMatches('/PHP internal/');

        (new Injector($container))->make(\SplFileObject::class, [
            'file_name' => __FILE__,
            // second parameter skipped
            // third parameter skipped
            'context' => null,
            'other-parameter' => true,
        ]);
    }

    public function testMakeAbstractClass(): void
    {
        $container = $this->getContainer();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');
        (new Injector($container))->make(LightEngine::class);
    }

    public function testMakeInterface(): void
    {
        $container = $this->getContainer();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');
        (new Injector($container))->make(EngineInterface::class);
    }

    public function testMakeWithVariadicFromContainer(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $object = (new Injector($container))->make(MakeEngineCollector::class, []);

        $this->assertCount(1, $object->engines);
        $this->assertSame([$container->get(EngineInterface::class)], $object->engines);
    }

    public function testMakeWithVariadicFromArguments(): void
    {
        $container = $this->getContainer();

        $values = [new EngineMarkTwo(), new EngineVAZ2101()];
        $object = (new Injector($container))->make(MakeEngineCollector::class, $values);

        $this->assertSame($values, $object->engines);
    }

    public function testMakeWithCustomParam(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $object = (new Injector($container))
            ->make(MakeEngineMatherWithParam::class, [new EngineVAZ2101(), 'parameter' => 'power']);

        $this->assertNotSame($object->engine1, $object->engine2);
        $this->assertInstanceOf(EngineVAZ2101::class, $object->engine1);
        $this->assertNotSame(EngineMarkTwo::class, $object->engine2);
        $this->assertSame($object->parameter, 'power');
    }

    public function testMakeWithInvalidCustomParam(): void
    {
        $container = $this->getContainer([EngineInterface::class => new EngineMarkTwo()]);

        $this->expectException(\TypeError::class);

        (new Injector($container))->make(MakeEngineMatherWithParam::class, ['parameter' => 100500]);
    }

    private function getContainer(array $definitions = []): ContainerInterface
    {
        return new class($definitions) implements ContainerInterface {
            private array $definitions;
            public function __construct(array $definitions = [])
            {
                $this->definitions = $definitions;
            }
            public function get($id)
            {
                if (!$this->has($id)) {
                    throw new class() extends \Exception implements NotFoundExceptionInterface {
                    };
                }
                return $this->definitions[$id];
            }
            public function has($id)
            {
                return array_key_exists($id, $this->definitions);
            }
        };
    }
}
