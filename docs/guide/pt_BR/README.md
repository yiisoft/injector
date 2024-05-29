# Yii Injector

## Uso geral

```php
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Injector\Injector;

$config = ContainerConfig::create()
    ->withDefinitions([
        EngineInterface::class => EngineMarkTwo::class,
    ]);
$container = new Container($config);

$getEngineName = static function (EngineInterface $engine) {
    return $engine->getName();
};

$injector = new Injector($container);
echo $injector->invoke($getEngineName);
// outputs "Mark Two"
```

No código acima, alimentamos nosso contêiner com `Injector` ao criá-lo. Qualquer recipiente [PSR-11](https://www.php-fig.org/psr/psr-11/)
poderia ser usado. Quando `invoke` é chamado, o injetor lê a assinatura do método invocado e, com base
na dica de tipo obtém automaticamente objetos para interfaces correspondentes do contêiner.

Às vezes você não tem um objeto no contêiner ou deseja especificar argumentos explicitamente. Isso poderia ser feito
como o seguinte:

```php
use Yiisoft\Injector\Injector;

/** @var $dataProvider DataProvider */
$dataProvider = /* ... */;
$result = (new Injector($container))->invoke([$calculator, 'calculate'], ['multiplier' => 5.0, $dataProvider]);
```

Acima, o método "calcular" se parece com o seguinte:

```php
public function calculate(DataProvider $dataProvider, float $multiplier)
{
    // ...
}
```

Passamos dois argumentos. Um é o `multiplier`. É explicitamente nomeado. Tais argumentos foram aprovados como estão. Outro é o
provedor de dados. Ele não é nomeado explicitamente, então o injetor encontra parâmetros correspondentes do mesmo tipo.

Criar uma instância de um objeto de uma determinada classe se comporta de forma semelhante a `invoke()`:
 
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

O objeto não é salvo no contêiner, então `make()` funciona bem para objetos criados dinamicamente de curta duração.

## Como funciona

Tanto `invoke()` quanto `make()` selecionam argumentos automaticamente para o método ou construtor chamado com base em
nomes/tipos de parâmetros e uma matriz opcional de valores explícitos.

O algoritmo é o seguinte:

![Algorithm](image/algorithm.svg)

Adicionalmente:

- Passar um argumento sem nome que não seja um objeto resulta em uma exceção.
- Cada argumento usado apenas uma vez.
- Argumentos explícitos não utilizados e sem nome são passados ​​no final da lista de argumentos. Seus valores poderiam ser obtidos com
 `func_get_args()`.
- Argumentos nomeados não utilizados são ignorados.
- Se os parâmetros aceitam argumentos por referência, os argumentos devem ser passados ​​explicitamente por referência:

  ```php
  use Yiisoft\Injector\Injector;
  
  $foo = 1;
  $increment = function (int &$value) {
      ++$value;
  };
  (new Injector($container))->invoke($increment, ['value' => &$foo]);
  echo $foo; // 2
  ```

## Cache de reflexão

`Injector` usa `Reflection API` para analisar assinaturas de classe. Por padrão, ele cria novos objetos `Reflection` a cada vez.
Chame `withCacheReflections(true)` para evitar esse comportamento e armazenar em cache objetos de reflexão.
Recomenda-se habilitar o cache no ambiente de produção, pois melhora o desempenho.
Se você usa estruturas assíncronas como `RoadRunner`, `AMPHP` ou `Swoole` não se esqueça de redefinir o estado do injetor.

```php
use Yiisoft\Injector\Injector;

$injector = (new Injector($container))
    ->withCacheReflections(true);
```
