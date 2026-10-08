# Container de DI

No Yii3, as classes não buscam o que precisam: elas pedem tudo no construtor, e o container de injeção de dependência monta os objetos. Isso vale para actions, serviços, middlewares e listeners. Este capítulo mostra como o container resolve as dependências sozinho e como configurá-lo quando ele não consegue.

O container vem do pacote `yiisoft/di`, e a sintaxe das definições é do `yiisoft/definitions`. O template já instala e cria o container a partir dos grupos `di` e `di-web` (ou `di-console`) da [configuração](/conceitos/configuracao).

## Autowiring

Quando o container precisa de uma classe que não tem definição, ele lê o construtor dela por reflexão e resolve cada parâmetro pelo tipo. É o autowiring. Foi assim que todas as actions deste guia receberam `WebViewRenderer`, `ResponseFactoryInterface` ou `SessionInterface` sem você escrever configuração.

O autowiring resolve sozinho:

- Parâmetros tipados com uma classe concreta: o container cria a instância, resolvendo as dependências dela do mesmo jeito.
- Parâmetros com valor padrão, quando não há outra informação: vale o padrão.

E precisa de ajuda em:

- Interfaces: o container não sabe qual implementação usar.
- Tipos escalares sem valor padrão (`string`, `int`, `array`): não há de onde tirar o valor.

Para esses casos você escreve uma definição.

Outra coisa importante: o container guarda cada objeto que cria. Pedir a mesma classe duas vezes devolve a mesma instância, durante toda a requisição. Isso tem consequências que o capítulo [Imutabilidade](/conceitos/imutabilidade) discute.

## O exemplo

Uma loja precisa avisar o cliente quando o pedido chega. A interface, em `src/Conceitos/Notificacao/NotificadorInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

interface NotificadorInterface
{
    public function enviar(string $para, string $mensagem): void;
}
```

Uma implementação que grava no log, em `src/Conceitos/Notificacao/NotificadorPorLog.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

use Psr\Log\LoggerInterface;

final readonly class NotificadorPorLog implements NotificadorInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private string $canal = 'geral',
    ) {}

    public function enviar(string $para, string $mensagem): void
    {
        $this->logger->info('[{canal}] Para {para}: {mensagem}', [
            'canal' => $this->canal,
            'para' => $para,
            'mensagem' => $mensagem,
            'category' => 'conceitos',
        ]);
    }
}
```

Outra que só guarda as mensagens em memória, útil em testes, em `src/Conceitos/Notificacao/NotificadorEmMemoria.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

final class NotificadorEmMemoria implements NotificadorInterface
{
    /** @var list<string> */
    private array $enviadas = [];

    public function enviar(string $para, string $mensagem): void
    {
        $this->enviadas[] = "$para: $mensagem";
    }

    /**
     * @return list<string>
     */
    public function enviadas(): array
    {
        return $this->enviadas;
    }
}
```

O serviço que usa o notificador, em `src/Conceitos/Notificacao/AvisoDePedido.php`. Ele depende de uma interface e de uma string, os dois casos que o autowiring não resolve:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

final class AvisoDePedido
{
    private string $prefixo = '';

    public function __construct(
        private readonly NotificadorInterface $notificador,
        private readonly string $remetente,
    ) {}

    public function usarPrefixo(string $prefixo): void
    {
        $this->prefixo = $prefixo . ' ';
    }

    public function avisar(string $cliente, int $pedido): string
    {
        $mensagem = "{$this->prefixo}Seu pedido $pedido foi recebido. Responda para {$this->remetente}.";
        $this->notificador->enviar($cliente, $mensagem);

        return $mensagem;
    }
}
```

E um segundo serviço, em `src/Conceitos/Notificacao/RelatorioDiario.php`:

```php
<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

final readonly class RelatorioDiario
{
    public function __construct(
        private NotificadorInterface $notificador,
    ) {}

    public function enviar(string $para, int $pedidos): void
    {
        $this->notificador->enviar($para, "Hoje foram $pedidos pedidos.");
    }
}
```

## As definições

As definições ficam em `config/common/di/conceitos.php`. Todo arquivo dessa pasta é carregado sozinho:

```php
<?php

declare(strict_types=1);

use App\Conceitos\Notificacao\AvisoDePedido;
use App\Conceitos\Notificacao\NotificadorEmMemoria;
use App\Conceitos\Notificacao\NotificadorInterface;
use App\Conceitos\Notificacao\NotificadorPorLog;
use App\Conceitos\Notificacao\RelatorioDiario;
use App\Conceitos\Saudacao;
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\Reference;

/** @var array $params */

return [
    Saudacao::class => [
        '__construct()' => [
            'mensagem' => $params['conceitos']['saudacao'],
            'itensPorPagina' => $params['conceitos']['itensPorPagina'],
        ],
    ],

    NotificadorInterface::class => NotificadorPorLog::class,

    NotificadorPorLog::class => static fn(LoggerInterface $logger) => new NotificadorPorLog($logger, canal: 'pedidos'),

    AvisoDePedido::class => [
        '__construct()' => [
            'remetente' => $params['conceitos']['remetente'],
        ],
        'usarPrefixo()' => ['[Guia Yii3]'],
    ],

    RelatorioDiario::class => [
        '__construct()' => [
            'notificador' => Reference::to(NotificadorEmMemoria::class),
        ],
    ],

    NotificadorEmMemoria::class => [
        'class' => NotificadorEmMemoria::class,
        'reset' => function (): void {
            $this->enviadas = [];
        },
    ],
];
```

A chave do array é o que se pede ao container, normalmente um nome de classe ou interface. O valor diz como construir. Cada formato a seguir aparece no arquivo.

### Interface para classe

```php
NotificadorInterface::class => NotificadorPorLog::class,
```

Quem pedir `NotificadorInterface` recebe um `NotificadorPorLog`. É a forma mais comum de definição: o código depende da interface, e a configuração escolhe a implementação. Trocar para um envio por e-mail é mudar esta linha.

### Array: class, `__construct()` e métodos

```php
AvisoDePedido::class => [
    '__construct()' => [
        'remetente' => $params['conceitos']['remetente'],
    ],
    'usarPrefixo()' => ['[Guia Yii3]'],
],
```

- `class`: a classe a instanciar. Pode ser omitida quando é a própria chave.
- `__construct()`: argumentos do construtor. Com chaves de texto, os argumentos vão pelo nome, e os que faltam continuam no autowiring. Aqui só `remetente` foi informado; o `notificador` vem da definição da interface.
- `metodo()`: um método chamado depois de criar o objeto, com os argumentos da lista.
- `$propriedade`: uma propriedade pública preenchida depois de criar o objeto, como `'$timeout' => 30`.

Métodos e propriedades são aplicados na ordem em que aparecem.

### Closure como fábrica

```php
NotificadorPorLog::class => static fn(LoggerInterface $logger) => new NotificadorPorLog($logger, canal: 'pedidos'),
```

Quando montar o objeto exige código, use uma função. Os parâmetros dela são resolvidos pelo container, como num construtor. Neste caso um array com `__construct()` daria o mesmo resultado; a closure vale a pena quando há lógica, como escolher a classe por uma condição ou chamar um método de fábrica de outra biblioteca. Se a lógica crescer, mova-a para um método estático de uma classe fábrica e use `[MinhaFabrica::class, 'criar']` como definição.

### Referências

```php
RelatorioDiario::class => [
    '__construct()' => [
        'notificador' => Reference::to(NotificadorEmMemoria::class),
    ],
],
```

Um nome de classe como argumento de `__construct()` seria tratado como string. O `Reference::to()` diz "busque isto no container". Aqui ele faz o `RelatorioDiario` usar o notificador em memória, enquanto o resto da aplicação continua com o notificador por log.

O `yiisoft/definitions` tem variações:

- `ReferencesArray::from([...])`: uma lista de referências, usada no `config/common/di/logger.php` para os targets do log.
- `DynamicReference::to(...)`: uma definição completa (array ou closure) usada como argumento. O objeto é criado novo, sem passar pelo cache do container. O template usa isso em `config/web/di/application.php` para montar o dispatcher de middlewares.

### Nomes que não são classes

A chave não precisa ser uma classe. O arquivo de log do app define `'log.pagamentos'` para um segundo `FileTarget`, e quem precisar dele usa `Reference::to('log.pagamentos')`. Isso resolve o caso de duas configurações diferentes da mesma classe.

## Testando

Uma action que usa os dois serviços, em `src/Web/Conceitos/Container/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Container;

use App\Conceitos\Notificacao\AvisoDePedido;
use App\Conceitos\Notificacao\NotificadorEmMemoria;
use App\Conceitos\Notificacao\RelatorioDiario;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private AvisoDePedido $aviso,
        private RelatorioDiario $relatorio,
        private NotificadorEmMemoria $memoria,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $mensagem = $this->aviso->avisar('ana@exemplo.com', 1001);
        $this->relatorio->enviar('gerente@exemplo.com', 12);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(
            "Aviso: $mensagem\n"
            . 'Em memória: ' . implode(' | ', $this->memoria->enviadas()) . "\n",
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Com a rota `/conceitos/container`:

```plaintext
Aviso: [Guia Yii3] Seu pedido 1001 foi recebido. Responda para loja@guia.test.
Em memória: gerente@exemplo.com: Hoje foram 12 pedidos.
```

- O prefixo veio do `usarPrefixo()` e o remetente dos params.
- O aviso foi para o log, pelo `NotificadorPorLog` com o canal `pedidos`: `runtime/logs/app.log` registra `[info][conceitos] [pedidos] Para ana@exemplo.com: ...`.
- O relatório foi para o `NotificadorEmMemoria`, e a action enxerga a mensagem porque recebeu a mesma instância que o `RelatorioDiario`.

## Reset

O último item da configuração tem a chave `reset`:

```php
NotificadorEmMemoria::class => [
    'class' => NotificadorEmMemoria::class,
    'reset' => function (): void {
        $this->enviadas = [];
    },
],
```

A função de reset devolve o serviço ao estado inicial. Ela roda com acesso às propriedades privadas do objeto (o `$this` é o próprio serviço) e é chamada pelo `Yiisoft\Di\StateResetter`. No PHP tradicional, cada requisição começa do zero e isso não importa. Em servidores de longa duração, como RoadRunner, o mesmo container atende várias requisições, e o runner chama o `StateResetter` entre elas. O exemplo completo está em [Imutabilidade](/conceitos/imutabilidade).

Para usar `reset` com uma closure, mova a closure para a chave `definition`: `['definition' => static fn() => ..., 'reset' => function () {...}]`. Da mesma forma funciona a chave `tags`, que agrupa serviços para receber todos de uma vez com `TagReference::to('nome')`.

## Pedindo ao container direto

Evite chamar `$container->get()` nas suas classes. Uma classe que recebe `ContainerInterface` para buscar dependências esconde do que depende e fica difícil de testar. Peça a dependência no construtor.

Quando você precisa de objetos novos a cada uso, e não da instância compartilhada, use o `Yiisoft\Factory\Factory`: ele aceita as mesmas definições, mas o `create()` monta um objeto novo a cada chamada.

::: tip
Com `APP_DEBUG=true`, o container valida as definições na inicialização. Uma chave errada, como `__contruct()`, gera logo na primeira requisição o erro `class "App\Conceitos\Saudacao" does not have the public method with name "__contruct"`, em vez de ser ignorada.
:::

## Comparando com o Yii2

- `Yii::$app->get('componente')` e `Yii::$container` viram o container injetado nos construtores. Não existe acesso global.
- A seção `components` vira as definições de `config/common/di/` e `config/web/di/`. Não há diferença entre "componente" e "serviço".
- O formato `['class' => ..., 'propriedade' => valor]` do Yii2 continua parecido, mas propriedades levam `$` na frente e chamadas de método levam `()`.
- `Yii::createObject()` vira o `Factory::create()`.
