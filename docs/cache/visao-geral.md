# Visão geral

Cache serve para guardar o resultado de uma operação cara (uma consulta pesada, uma chamada a outro serviço, um cálculo) e reaproveitar esse resultado nas próximas requisições. No Yii3, isso fica no pacote `yiisoft/cache`, construído em cima da PSR-16. Este capítulo mostra as duas interfaces do pacote, os drivers disponíveis e como configurar o cache em arquivo no app.

## Instalando

```bash
composer require yiisoft/cache yiisoft/cache-file
```

O `yiisoft/cache` já veio no capítulo [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados), porque a conexão guarda a estrutura das tabelas em cache. O `yiisoft/cache-file` traz o driver que grava em arquivos.

## Duas interfaces

O pacote trabalha com duas interfaces, e vale a pena separar bem o papel de cada uma.

A primeira é a `Psr\SimpleCache\CacheInterface`, da PSR-16. É o contrato que todo driver implementa: `get()`, `set()`, `delete()`, `has()`, `clear()` e as versões em lote `getMultiple()`, `setMultiple()` e `deleteMultiple()`. É uma interface pequena e sem regra nenhuma além de guardar e devolver valores.

A segunda é a `Yiisoft\Cache\CacheInterface`, implementada pela classe `Yiisoft\Cache\Cache`. Ela embrulha um driver PSR-16 e acrescenta:

- `getOrSet()`, que busca o valor e, se não existir, calcula e grava numa chamada só;
- dependências, para invalidar um valor quando algo muda (por exemplo, uma tag);
- proteção contra o efeito manada, quando muitas requisições tentam recalcular o mesmo valor ao mesmo tempo;
- um TTL padrão para os valores gravados por `getOrSet()`.

O método `psr()` devolve a interface PSR-16 por trás do `Cache`, para quando você quer só `get()` e `set()`. Os detalhes de `getOrSet()` e das dependências estão em [Cache de dados](/cache/cache-de-dados).

## Drivers

O driver é a implementação da PSR-16 que decide onde os dados ficam.

| Pacote | Driver | Onde os dados ficam |
|---|---|---|
| `yiisoft/cache` | `ArrayCache` | Num array, na memória do processo. Some ao fim da requisição. |
| `yiisoft/cache` | `NullCache` | Em lugar nenhum. Toda leitura é um "não encontrado". |
| `yiisoft/cache-file` | `FileCache` | Em arquivos, numa pasta do servidor. |
| `yiisoft/cache-redis` | Redis | Num servidor Redis. |
| `yiisoft/cache-memcached` | Memcached | Num servidor Memcached. |
| `yiisoft/cache-apcu` | APCu | Na memória compartilhada do PHP, no próprio servidor. |
| `yiisoft/cache-db` | Banco de dados | Numa tabela do banco. |

O `ArrayCache` serve para testes e para guardar algo durante uma única requisição. O `NullCache` desliga o cache sem mexer no código que o usa. O `FileCache` funciona em qualquer hospedagem e é o que usamos no app de exemplo. Quando a aplicação roda em mais de um servidor, o cache precisa ser compartilhado entre eles, e aí entra o Redis ou o Memcached. Esses pacotes não estão instalados no app de exemplo: para usar um deles, você instala o pacote e troca o driver na configuração mostrada a seguir. O código que usa o cache não muda.

## O que vem configurado

O próprio `yiisoft/cache` registra no container duas definições, no arquivo `config/di.php` do pacote:

- `Yiisoft\Cache\CacheInterface` aponta para `Yiisoft\Cache\Cache`;
- `Psr\SimpleCache\CacheInterface` aponta para `ArrayCache`.

Ou seja, sem configurar nada, o cache funciona, mas guarda tudo na memória e perde tudo no fim da requisição. Para que os valores sobrevivam entre requisições, você precisa dizer ao container qual driver usar.

## Configurando o cache em arquivo

Crie `config/common/di/cache.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Cache\Cache;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\File\FileCache;
use Yiisoft\Cache\Ttl;

return [
    CacheInterface::class => static fn (FileCache $fileCache) => new Cache($fileCache, Ttl::hours(1)),
];
```

A partir de agora, quem pedir `Yiisoft\Cache\CacheInterface` no construtor recebe um `Cache` que grava em arquivos, com TTL padrão de uma hora. O `Ttl` é um objeto pequeno que evita contas como `60 * 60`: há `Ttl::seconds()`, `Ttl::minutes()`, `Ttl::hours()`, `Ttl::days()` e `Ttl::forever()`.

O `FileCache` não precisa de definição: o `yiisoft/cache-file` já registra uma, que grava em `@runtime/cache`. Para mudar a pasta, sobrescreva o parâmetro no arquivo de parâmetros:

```php
'yiisoft/cache-file' => [
    'fileCache' => [
        'path' => '@runtime/cache',
    ],
],
```

Repare que configuramos só a interface do Yii. A `Psr\SimpleCache\CacheInterface` continua apontando para o `ArrayCache`. Isso é proposital: o roteador (`yiisoft/router-fastroute`) recebe essa interface e guarda nela as rotas compiladas, na chave `routes-cache`. Com o `ArrayCache`, isso dura uma requisição. Com um cache em arquivo, as rotas ficariam salvas, e uma rota nova só apareceria depois de limpar o cache. Se você quiser apontar a interface PSR-16 para um driver persistente, desligue o cache do roteador em desenvolvimento com o parâmetro `'yiisoft/router-fastroute' => ['enableCache' => false]`, ou limpe a pasta do cache a cada deploy.

## Usando a interface PSR-16

Para ver o cache funcionando, vamos fazer um contador de visitas. Crie `src/Web/Cache/Contador/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Cache\Contador;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Cache\CacheInterface;

final readonly class Action
{
    public function __construct(
        private CacheInterface $cache,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $psr = $this->cache->psr();

        $visitas = $psr->get('cache.contador', 0) + 1;
        $psr->set('cache.contador', $visitas, 60);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Visitas: $visitas\n");

        return $response;
    }
}
```

- `get()` recebe a chave e um valor padrão, devolvido quando a chave não existe ou expirou.
- `set()` recebe a chave, o valor e o TTL em segundos. Aqui o TTL é renovado a cada visita, então o contador zera depois de um minuto sem acessos.
- As chaves da PSR-16 são strings e não podem ter os caracteres `{}()/\@:`. Por isso usamos `cache.contador`.

O TTL padrão de uma hora, configurado no `Cache`, vale só para o `getOrSet()`. Pelo `psr()`, um `set()` sem TTL grava o valor sem expiração.

No arquivo de rotas, os exemplos desta seção ficam num grupo com o prefixo `/cache`:

```php
use App\Web\Cache;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

Group::create('/cache')
    ->namePrefix('cache/')
    ->routes(
        Route::get('/contador')
            ->action(Cache\Contador\Action::class)
            ->name('contador'),
    ),
```

## Testando

```bash
curl http://127.0.0.1:8080/cache/contador
curl http://127.0.0.1:8080/cache/contador
curl http://127.0.0.1:8080/cache/contador
```

```plaintext
Visitas: 1
Visitas: 2
Visitas: 3
```

O valor sobreviveu entre as requisições porque está gravado em `runtime/cache/ca/cache.contador.bin`. O `FileCache` cria subpastas com os dois primeiros caracteres da chave, para não juntar milhares de arquivos numa pasta só.

Este contador tem um problema: entre o `get()` e o `set()`, outra requisição pode ler o mesmo valor, e uma visita se perde. A PSR-16 não tem operação de incremento atômico. Para contadores exatos, use o banco ou um recurso do próprio Redis.

## Separando aplicações com prefixo

Quando duas aplicações dividem o mesmo Redis, uma pode ler as chaves da outra. O `Yiisoft\Cache\PrefixedCache` resolve isso: ele embrulha um driver PSR-16 e acrescenta um prefixo a todas as chaves. Você o coloca entre o driver e o `Cache`, por exemplo `new Cache(new PrefixedCache($driver, 'loja_'))`.

## Comparando com o Yii2

No Yii2, o cache era o componente `cache` da aplicação, configurado com uma classe como `yii\caching\FileCache` e acessado por `Yii::$app->cache`. No Yii3:

- O driver é uma implementação da PSR-16, escolhida no container de DI. Não existe `Yii::$app`: a classe recebe o cache no construtor.
- O `getOrSet()` e as dependências continuam existindo, na classe `Cache`.
- Os drivers são pacotes separados. O pacote principal só traz `ArrayCache` e `NullCache`.
