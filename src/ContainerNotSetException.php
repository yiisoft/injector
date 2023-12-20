<?php

declare(strict_types=1);

namespace Yiisoft\Injector;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

final class ContainerNotSetException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(string $class)
    {
        parent::__construct(
            sprintf(
                'Container is not set in injector, so impossible resolve "%s".',
                $class
            )
        );
    }
}
