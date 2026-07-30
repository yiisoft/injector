<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Yiisoft\CodeStyle\Rector\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhp74Sets()
    ->withSets([
        SetList::YII_CORE,
    ])
    ->withSkip([
        __DIR__ . '/tests/Php8',
    ]);
