<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8\Support;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;

class TypesIntersection
{
    public function __construct(
        public ArrayAccess&Countable $collection
    ) {
    }

    public function getCollection(): ArrayAccess&Countable
    {
        return $this->collection;
    }

    public static function getClosure(): Closure
    {
        return static fn (ArrayAccess&Countable $collection = new ArrayIterator()): ArrayAccess&Countable => $collection;
    }
}
