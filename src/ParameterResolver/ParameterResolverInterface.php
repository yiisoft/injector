<?php

declare(strict_types=1);

namespace Yiisoft\Injector\ParameterResolver;

use ReflectionParameter;

interface ParameterResolverInterface
{
    /**
     * @throws ParameterNotResolvedException
     *
     * @return mixed
     */
    public function resolve(ReflectionParameter $parameter);
}
