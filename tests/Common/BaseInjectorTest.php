<?php

declare(strict_types=1);

namespace Yiisoft\Injector\Tests\Common;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

abstract class BaseInjectorTest extends TestCase
{
    protected function getContainer(array $definitions = []): ContainerInterface
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
