<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="100px">
    </a>
    <h1 align="center">Yii Injector</h1>
    <br>
</p>

The package is [PSR-11](http://www.php-fig.org/psr/psr-11/) compatible injector that is able to invoke methods or create objects resolving their dependencies
via autowiring.

[![Latest Stable Version](https://poser.pugx.org/yiisoft/injector/v/stable.png)](https://packagist.org/packages/yiisoft/injector)
[![Total Downloads](https://poser.pugx.org/yiisoft/injector/downloads.png)](https://packagist.org/packages/yiisoft/injector)
[![Build status](https://github.com/yiisoft/injector/workflows/build/badge.svg)](https://github.com/yiisoft/injector/actions)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/injector/badges/coverage.png)](https://scrutinizer-ci.com/g/yiisoft/injector/)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/injector/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/injector/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Finjector%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/injector/master)
[![static analysis](https://github.com/yiisoft/injector/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/injector/actions?query=workflow%3A%22static+analysis%22)

## Features

- Invoke callable or create an object of a given class.
- Resolve dependencies by parameter types using the given [PSR-11](http://www.php-fig.org/psr/psr-11/) container.
- Pass concrete dependency instances by type.
- Pass arguments by name.

## Installation

The preferred way to install this package is through [composer](http://getcomposer.org/download/):

```
composer require yiisoft/injector
```

## Documentation

- [English](docs/en/README.md)
- [Russian](docs/ru/README.md)
