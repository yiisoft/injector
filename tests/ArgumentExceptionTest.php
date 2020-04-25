<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Injector\ArgumentException;
use Yiisoft\Injector\Tests\Support\EngineVAZ2101;
use Yiisoft\Injector\Tests\Support\MakeEngineMatherWithParam;

function testFunction(): void
{
    return;
}

abstract class ArgumentExceptionTest extends TestCase
{
    protected const EXCEPTION_CLASS_NAME = '';

    public function testConstructorReflection(): void
    {
        $class = MakeEngineMatherWithParam::class;
        $reflection = (new \ReflectionClass($class))->getConstructor();
        $exception = $this->createException($reflection, 'parameter');

        $this->assertStringContainsString("{$class}::__construct", $exception->getMessage());
    }
    public function testMethodReflection(): void
    {
        $class = EngineVAZ2101::class;
        $method = 'rust';
        $classReflection = new \ReflectionClass($class);
        $reflection = $classReflection->getMethod($method);
        $exception = $this->createException($reflection, 'index');

        $this->assertStringContainsString("{$class}::{$method}", $exception->getMessage());
        $this->assertStringContainsString('index', $exception->getMessage());
    }
    public function testClosureReflection(): void
    {
        // $callable = \Closure::fromCallable($callable);
        $reflection = new \ReflectionFunction(fn (bool $toInverse) => !$toInverse);
        $exception = $this->createException($reflection, 'toInverse');

        $this->assertStringContainsString(__NAMESPACE__ . '\\{closure}', $exception->getMessage());
        $this->assertStringContainsString('toInverse', $exception->getMessage());
    }
    public function testInternalStaticCallableReflection(): void
    {
        $callable = \Closure::fromCallable('\DateTimeImmutable::createFromMutable');
        $reflection = new \ReflectionFunction($callable);
        $exception = $this->createException($reflection, 'anyParameter');

        $this->assertStringContainsString('createFromMutable', $exception->getMessage());
        $this->assertStringContainsString('anyParameter', $exception->getMessage());
    }
    public function testInternalFunctionReflection(): void
    {
        $reflection = new \ReflectionFunction('\\array_map');
        $exception = $this->createException($reflection, 'anyParameter');

        $this->assertStringContainsString('array_map', $exception->getMessage());
        $this->assertStringNotContainsString('\\array_map', $exception->getMessage());
        $this->assertStringContainsString('anyParameter', $exception->getMessage());
    }
    public function testUserFunctionInNameSpaceReflection(): void
    {
        $reflection = new \ReflectionFunction(__NAMESPACE__ . '\\testFunction');
        $exception = $this->createException($reflection, 'anyParameter');

        $this->assertStringContainsString(__NAMESPACE__ . '\\testFunction', $exception->getMessage());
        $this->assertStringContainsString('anyParameter', $exception->getMessage());
    }

    protected function createException(\ReflectionFunctionAbstract $reflection, string $parameter): ArgumentException
    {
        $class = static::EXCEPTION_CLASS_NAME;
        /** @var ArgumentException $exception */
        $exception = new $class($reflection, $parameter);
        return $exception;
    }
}
