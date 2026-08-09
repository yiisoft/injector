<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = (new Configuration())
    ->disableComposerAutoloadPathScan()
    ->setFileExtensions(['php'])
    ->addPathToScan(__DIR__ . '/src', false)
    ->addPathToScan(__DIR__ . '/tests', true)
    ->ignoreErrorsOnPackages(['psr/container'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);

if (PHP_VERSION_ID < 80000) {
    $config->ignoreUnknownClasses(['ReflectionUnionType']);
}

if (PHP_VERSION_ID < 80100) {
    $config->ignoreUnknownClasses(['ReflectionEnum', 'ReflectionIntersectionType']);
}

return $config;
