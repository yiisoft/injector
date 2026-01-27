<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8;

use ArrayAccess;
use ArrayIterator;
use Countable;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\MissingRequiredArgumentException;
use Yiisoft\Injector\Tests\Common\BaseInjectorTest;
use Yiisoft\Injector\Tests\Php8\Support\IntEnum;
use Yiisoft\Injector\Tests\Php8\Support\NonBackedEnum;
use Yiisoft\Injector\Tests\Php8\Support\StrEnum;
use Yiisoft\Injector\Tests\Php8\Support\TimerUnionTypes;
use Yiisoft\Injector\Tests\Php8\Support\TypesIntersection;
use Yiisoft\Injector\Tests\Php8\Support\TypesIntersectionReferencedConstructor;

class InjectorTest extends BaseInjectorTest
{
    public function testMakeInternalClass(): void
    {
        $container = $this->getContainer();

        $object = (new Injector($container))->make(\SplFileObject::class, [
            'filename' => __FILE__,
            // second parameter skipped
            // third parameter skipped
            'context' => null,
            'other-parameter' => true,
        ]);

        $this->assertSame(basename(__FILE__), $object->getFilename());
    }

    public function testInvokeUnionTypes(): void
    {
        $time = new DateTimeImmutable();
        $container = $this->getContainer([DateTimeInterface::class => $time]);

        $object = (new Injector($container))
            ->make(TimerUnionTypes::class);

        $this->assertSame($object->getTime(), $time);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTypeIntersection(): void
    {
        $argument = new ArrayIterator();
        $container = $this->getContainer();

        $object = (new Injector($container))
            ->make(TypesIntersection::class, [$argument]);

        $this->assertSame($argument, $object->collection);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTypeIntersectionReferenced(): void
    {
        $obj1 = new ArrayIterator();
        $obj2 = new ArrayIterator();
        $container = $this->getContainer();

        $object = (new Injector($container))
            ->make(TypesIntersectionReferencedConstructor::class, [&$obj1]);
        $object->collection = $obj2;

        $this->assertSame($obj1, $obj2);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTypeIntersectionVariadic(): void
    {
        $obj1 = new ArrayIterator();
        $obj2 = new ArrayIterator();
        $obj3 = new ArrayIterator();
        $obj4 = new ArrayIterator();
        $container = $this->getContainer();

        $result = (new Injector($container))
            ->invoke([new TypesIntersection($obj1), 'getVariadic'], [
                new stdClass(),
                $obj1,
                new stdClass(),
                $obj2,
                new stdClass(),
                $obj3,
                new stdClass(),
                $obj4,
                new stdClass(),
            ]);

        $this->assertSame([$obj1, $obj2, $obj3, $obj4], $result);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testResolveMultiple(): void
    {
        $obj1 = new ArrayIterator();
        $obj2 = new ArrayIterator();
        $obj3 = new ArrayIterator();
        $obj4 = new ArrayIterator();
        $container = $this->getContainer();

        $result = (new Injector($container))
            ->invoke([new TypesIntersection($obj1), 'getMultiple'], [
                new stdClass(),
                $obj1,
                new stdClass(),
                $obj2,
                new stdClass(),
                $obj3,
                new stdClass(),
                $obj4,
                new stdClass(),
            ]);

        $this->assertSame($obj1, $result[0]);
        $this->assertSame($obj2, $result[1]);
        $this->assertSame($obj3, $result[2]);
        $this->assertSame($obj4, $result[3]);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTypeIntersectionMustNotBePulledFromContainer(): void
    {
        $collection = new ArrayIterator();
        $container = $this->getContainer([
            ArrayAccess::class => $collection,
            Countable::class => $collection,
        ]);

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))
            ->make(TypesIntersection::class);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testTypeIntersectionNotResolved(): void
    {
        $container = $this->getContainer();

        $this->expectException(MissingRequiredArgumentException::class);

        (new Injector($container))->make(TypesIntersection::class);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testResolveEnumFromContainer(): void
    {
        $str = $this->createEnumValue(StrEnum::class, 'Bar');
        $int = $this->createEnumValue(IntEnum::class, 'Bar');
        $nb = $this->createEnumValue(NonBackedEnum::class, 'Bar');
        $container = $this->getContainer([
            StrEnum::class => $str,
            IntEnum::class => $int,
            NonBackedEnum::class => $nb,
        ]);

        $result = (new Injector($container))
            ->invoke(static fn (StrEnum $arg1, IntEnum $arg2, NonBackedEnum $arg3) => [$arg1, $arg2, $arg3]);

        $this->assertSame([$str, $int, $nb], $result);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testResolveEnumFromArguments(): void
    {
        $str = $this->createEnumValue(StrEnum::class, 'Bar');
        $int = $this->createEnumValue(IntEnum::class, 'Bar');
        $nb = $this->createEnumValue(NonBackedEnum::class, 'Bar');
        $container = $this->getContainer();

        $result = (new Injector($container))
            ->invoke(
                static fn (StrEnum $arg1, IntEnum $arg2, NonBackedEnum $arg3) => [$arg1, $arg2, $arg3],
                [$nb, $int, $str]
            );

        $this->assertSame([$str, $int, $nb], $result);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testEnumCanNotBeAutowired(): void
    {
        $container = $this->getContainer();

        $this->expectException(NotFoundExceptionInterface::class);

        (new Injector($container))
            ->invoke(static fn (StrEnum $arg1, IntEnum $arg2) => [$arg1, $arg2]);
    }

    private function createEnumValue(string $enumClass, string $case)
    {
        $reflection = new \ReflectionEnum($enumClass);
        return $reflection->getCase($case)->getValue();
    }
}
