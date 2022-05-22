<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8\Support;

use ArrayAccess;
use Countable;

class TypesIntersectionReferencedConstructor
{
    public function __construct(
        public ArrayAccess&Countable &$collection
    ) {
    }
}
