<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8;

use ArrayAccess;
use ArrayIterator;
use Countable;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\Tests\Common\BaseInjectorTest;
use Yiisoft\Injector\Tests\Php8\Support\TimerUnionTypes;
use Yiisoft\Injector\Tests\Php8\Support\TypesIntersection;

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

    public function testTypeIntersection(): void
    {
        $argument = new ArrayIterator();
        $container = $this->getContainer();

        $object = (new Injector($container))
            ->make(TypesIntersection::class, [$argument]);

        self::assertSame($argument, $object->collection);
    }

    public function testTypeIntersectionFromContainer(): void
    {
        $collection = new ArrayIterator();
        $container = $this->getContainer([
            ArrayAccess::class => $collection,
            Countable::class => $collection,
        ]);

        $this->expectException(Exception::class);

        (new Injector($container))
            ->make(TypesIntersection::class);
    }
}
