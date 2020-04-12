# Injector package


## Features

- Invoke callable or create and object of a given class.
- Resolve dependencies by parameter types using the given [PSR-11](http://www.php-fig.org/psr/psr-11/) container.
- Pass concrete dependency instances by type.
- Pass arguments by name.

## General usage

```php
use Yiisoft\Di\Container;
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

Sometimes you either don't have an object in container or want to explicitly specify arguments. It could be done
like the following:

```php
use Yiisoft\Injector\Injector;

/** @var $dataProvider DataProvider */
$dataProvider = /* ... */;
$result = (new Injector($container))->invoke([$calculator, 'calculate'], ['multiplier' => 5.0, $dataProvider]);
```

In the above the "calculate" method looks like the following:

```php
public function calculate(DataProvider $dataProvider, float $multiplier)
{
    // ...
}
```

We have passed two arguments. One is `multiplier`. It is explicitly named. Such arguments passed as is. Another is 
data provider. It is not named explicitly so injector finds matching parameter that has the same type.

Creating an instance of an object of a given class behaves similar to `invoke()`:

```php
use Yiisoft\Injector\Injector;

class StringFormatter
{
    public function __construct($string, \Yiisoft\I18n\MessageFormatterInterface $formatter)
    {
        // ...
    }
    public function getFormattedString(): string
    {
        // ...
    }
}

$stringFormatter = (new Injector($container))->make(StringFormatter::class, ['string' => 'Hello World!']);

$result = $stringFormatter->getFormattedString();
```
