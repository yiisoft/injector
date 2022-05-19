<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8;

use PHPUnit\Framework\TestCase;
use Yiisoft\Injector\ArgumentException;
use Yiisoft\Injector\Tests\Php8\Support\TypesIntersection;

abstract class ArgumentExceptionTest extends TestCase
{
    protected const EXCEPTION_CLASS_NAME = '';

    public function testRichClosureReflectionUnionTypes(): void
    {
        $reflection = new \ReflectionFunction(
            static function (\DateTimeImmutable|\DateTime|string|int $datetime): void {
                array_map(null, func_get_args());
            }
        );
        $exception = $this->createException($reflection, 'datetime');

        $this->assertStringContainsString(
            'static function (DateTimeImmutable|DateTime|string|int $datetime)',
            $exception->getMessage()
        );
    }

    public function testRenderNotStaticClosure(): void
    {
        $reflection = new \ReflectionFunction(
            function (string|int $datetime): void {
                array_map(null, func_get_args());
            }
        );
        $exception = $this->createException($reflection, 'datetime');

        $this->assertStringContainsString(
            'function (string|int $datetime)',
            $exception->getMessage()
        );
        $this->assertStringNotContainsString(
            'static',
            $exception->getMessage()
        );
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testRenderTypesIntersectionClosureWithDefaultObjectParamValue(): void
    {
        $reflection = new \ReflectionFunction(
            TypesIntersection::getClosure()
        );
        $exception = $this->createException($reflection, 'datetime');

        $this->assertStringContainsString(
            'static function (ArrayAccess&Countable $collection = new ArrayIterator(...))',
            $exception->getMessage()
        );
    }

    protected function createException(\ReflectionFunctionAbstract $reflection, string $parameter): ArgumentException
    {
        $class = static::EXCEPTION_CLASS_NAME;
        /** @var ArgumentException $exception */
        $exception = new $class($reflection, $parameter);
        return $exception;
    }
}
