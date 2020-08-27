<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Php8;

use PHPUnit\Framework\TestCase;
use Yiisoft\Injector\ArgumentException;

abstract class ArgumentExceptionTest extends TestCase
{
    protected const EXCEPTION_CLASS_NAME = '';

    public function testRichClosureReflectionUnionTypes(): void
    {
        $reflection = new \ReflectionFunction(static function (\DateTimeImmutable|\DateTime|string|int $datetime): void {
            array_map(null, func_get_args());
        });
        $exception = $this->createException($reflection, 'datetime');

        $this->assertStringContainsString(
            'function (DateTimeImmutable|DateTime|string|int $datetime)',
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
