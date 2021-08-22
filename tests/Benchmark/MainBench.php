<?php
declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Benchmark;

use Generator;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;
use Yiisoft\Injector\Injector;
use Yiisoft\Injector\Tests\Common\Support\EngineInterface;
use Yiisoft\Injector\Tests\Common\Support\EngineMarkTwo;
use Yiisoft\Injector\Tests\Common\Support\MakeEmptyConstructor;
use Yiisoft\Injector\Tests\Common\Support\MakeEngineCollector;
use Yiisoft\Injector\Tests\Common\Support\MakeNoConstructor;
use Yiisoft\Test\Support\Container\SimpleContainer;

/**
 * @BeforeMethods("setUp")
 */
final class MainBench
{
    private Injector $injector;

    public function setUp(array $params): void
    {
        $container = new SimpleContainer($params['definitions']);
        $this->injector = new Injector($container);
    }

    /**
     * @Revs(10000)
     * @Iterations(10)
     * @ParamProviders("provider")
     * @Warmup(1)
     */
    public function benchMake(array $params): void
    {
        $this->injector->make($params['class']);
    }

    public function provider(): Generator
    {
        yield 'without constructor' => [
            'class' => MakeNoConstructor::class,
            'definitions' => [],
        ];
        yield 'with empty constructor' => [
            'class' => MakeEmptyConstructor::class,
            'definitions' => [],
        ];
        yield 'with not empty constructor' => [
            'class' => MakeEngineCollector::class,
            'definitions' => [EngineInterface::class => new EngineMarkTwo()],
        ];
    }
}
