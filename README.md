<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="100px">
    </a>
    <h1 align="center">Yii Injector</h1>
    <br>
</p>

A [dependency injection](http://en.wikipedia.org/wiki/Dependency_injection)
implementation based on autowiring and
[PSR-11](http://www.php-fig.org/psr/psr-11/) compatible dependency injection containers.

[![Latest Stable Version](https://poser.pugx.org/yiisoft/injector/v/stable.png)](https://packagist.org/packages/yiisoft/injector)
[![Total Downloads](https://poser.pugx.org/yiisoft/injector/downloads.png)](https://packagist.org/packages/yiisoft/injector)
[![Build status](https://github.com/yiisoft/injector/workflows/build/badge.svg)](https://github.com/yiisoft/injector/actions)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/injector/badges/coverage.png)](https://scrutinizer-ci.com/g/yiisoft/injector/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/injector/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/injector/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Finjector%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/injector/master)
[![static analysis](https://github.com/yiisoft/injector/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/injector/actions?query=workflow%3A%22static+analysis%22)

## Features

 * Injects dependencies when calling functions and creating objects
 * Works with any dependency injection container (DIC) that is [PSR-11](http://www.php-fig.org/psr/psr-11/) compatible
 * Accepts additional dependencies and arguments passed as array
 * Allows passing arguments *by parameter name* in the array
 * Resolves object type dependencies from the container and the passed array
   by [parameter type declaration](https://www.php.net/manual/en/functions.arguments.php#functions.arguments.type-declaration)
 * Resolves [variadic arguments](https://www.php.net/manual/en/functions.arguments.php#functions.variable-arg-list)
   i.e. `function (MyClass ...$a)`


## About

Injector can automatically resolve and inject dependencies when calling
functions and creating objects.

It therefore uses [Reflection](https://www.php.net/manual/en/book.reflection.php) to analyze the
parameters of the function to call, or the constructor of the class to
instantiate and then tries to resolve all arguments by several strategies.

The main purpose is to find dependency objects - that is arguments of type
object that are declared with a classname or an interface - in a (mandatory)
[PSR-11](http://www.php-fig.org/psr/psr-11/) compatible *dependency injection
container* (DIC). The container must therefore use the class or interface name
as ID.

In addition, an array with arguments can be passed that will also be scanned for
matching dependencies. To make things really flexible (and not limited to
objects), arguments in that array can optionally use a function parameter name
as key. This way basically any callable can be invoked and any object
be instantiated by the Injector even if it uses a mix of object dependencies and
arguments of other types.


## Basic Example

```php
// A function to call
$fn = function (\App\Foo $a, \App\Bar $b, int $c) { /* ... */ };

// Arbitrary PSR-11 compatible object container
$container = new \some\di\Container([
    'App\Foo' => new Foo(), // will be used as $a
]);

// Prepare the injector
$injector = new Injector($container);

// Use the injector to call the function and resolve dependencies
$result = $injector->invoke($fn, [
    'c' => 15,  // will be used as $c
    new Bar(),  // will be used as $b
]);
```


## Installation

The preferred way to install this package is through [composer](http://getcomposer.org/download/):

```
composer require yiisoft/injector
```

## Documentation

- [English](docs/en/README.md)
- [Russian](docs/ru/README.md)
