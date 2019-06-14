<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="100px">
    </a>
    <h1 align="center">Yii Injector</h1>
    <br>
</p>

The package is PSR-11 compatible injector. Given a DI container and a callable it injects objects from DI container
based on types specified in callable signature.

[![Latest Stable Version](https://poser.pugx.org/yiisoft/injector/v/stable.png)](https://packagist.org/packages/yiisoft/injector)
[![Total Downloads](https://poser.pugx.org/yiisoft/injector/downloads.png)](https://packagist.org/packages/yiisoft/injector)
[![Build Status](https://travis-ci.com/yiisoft/injector.svg?branch=master)](https://travis-ci.com/yiisoft/injector)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/injector/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/injector/?branch=master)

## General usage

```php
$callable = function (CacheInterface $cache) {
    // ...
}

$result = (new Injector($container))->invoke($callable);
```
