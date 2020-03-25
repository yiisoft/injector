<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="100px">
    </a>
    <h1 align="center">Yii Injector</h1>
    <br>
</p>

The package is PSR-11 compatible injector that is able to invoke methods resolving their dependencies via autowiring.

[![Latest Stable Version](https://poser.pugx.org/yiisoft/injector/v/stable.png)](https://packagist.org/packages/yiisoft/injector)
[![Total Downloads](https://poser.pugx.org/yiisoft/injector/downloads.png)](https://packagist.org/packages/yiisoft/injector)
[![Build Status](https://travis-ci.com/yiisoft/injector.svg?branch=master)](https://travis-ci.com/yiisoft/injector)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/injector/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/injector/?branch=master)

## General usage

```php
use Yiisoft\Injector\Injector;

$container = new Container([
    EngineInterface::class => EngineMarkTwo::class,
]);

$getEngineName = static function (EngineInterface $engine) {
    return $engine->getName();
};

$injector = new Injector($container);
echo $injector->invoke($getEngineName);
// outputs "Mark Two"
```

In the code above we feed our container to `Injector` when creating it. Any PSR-11 container could be used.
When `invoke` is called, injector reads method signature of the method invoked and, based on type hinting
automatically obtains objects for corresponding interfaces from the container.
