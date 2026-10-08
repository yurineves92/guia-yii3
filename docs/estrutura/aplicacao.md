# Aplicação

No Yii2, a aplicação era um objeto enorme: guardava a configuração, os componentes, o request, o usuário logado e ainda era acessível de qualquer lugar por `Yii::$app`. No Yii3, a classe `Application` faz uma coisa só: recebe uma requisição, passa pela pilha de middlewares e devolve uma resposta. Este capítulo mostra essa classe, o handler que responde quando nenhuma rota casa e onde ficam os parâmetros que antes iam em `Yii::$app->params`.

## A classe Application

A classe é `Yiisoft\Yii\Http\Application`, do pacote `yiisoft/yii-http`. O construtor recebe três dependências:

- `MiddlewareDispatcher $dispatcher`: a pilha de middlewares global.
- `?EventDispatcherInterface $eventDispatcher`: opcional, para disparar os eventos da aplicação.
- `RequestHandlerInterface $fallbackHandler`: o handler chamado quando nenhum middleware devolveu resposta.

O método principal é o `handle()`. Ele é curto o bastante para ler inteiro:

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    $this->eventDispatcher?->dispatch(new BeforeRequest($request));

    try {
        return $response = $this->dispatcher->dispatch($request, $this->fallbackHandler);
    } finally {
        $this->eventDispatcher?->dispatch(new AfterRequest($response ?? null));
    }
}
```

Além dele, há `start()`, `afterEmit()` e `shutdown()`, que só disparam eventos. O runner chama os quatro nesta ordem, e cada um dispara um evento do namespace `Yiisoft\Yii\Http\Event`:

| Método | Evento |
|---|---|
| `start()` | `ApplicationStartup` |
| `handle()` | `BeforeRequest`, depois `AfterRequest` |
| `afterEmit()` | `AfterEmit` (depois de enviar a resposta) |
| `shutdown()` | `ApplicationShutdown` |

Esses eventos são o lugar para código que precisa rodar em toda requisição sem ser um middleware, como fechar conexões ou gravar métricas depois que a resposta saiu. Como registrar listeners está em [Eventos](/conceitos/eventos).

### Como a aplicação é montada

A `Application` é criada pelo container, a partir da definição em `config/web/di/application.php`. É assim que o arquivo vem no template (o app de exemplo deste guia troca uma linha, a do CSRF, pelo motivo explicado em [APIs REST](/api/inicio-rapido#o-csrf-e-a-api)):

```php
<?php

declare(strict_types=1);

use App\Web\NotFound\NotFoundHandler;
use Yiisoft\Csrf\CsrfTokenMiddleware;
use Yiisoft\Definitions\DynamicReference;
use Yiisoft\Definitions\Reference;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Input\Http\HydratorAttributeParametersResolver;
use Yiisoft\Input\Http\RequestInputParametersResolver;
use Yiisoft\Middleware\Dispatcher\CompositeParametersResolver;
use Yiisoft\Middleware\Dispatcher\MiddlewareDispatcher;
use Yiisoft\Middleware\Dispatcher\ParametersResolverInterface;
use Yiisoft\RequestProvider\RequestCatcherMiddleware;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Session\SessionMiddleware;
use Yiisoft\Yii\Http\Application;

/** @var array $params */

return [
    Application::class => [
        '__construct()' => [
            'dispatcher' => DynamicReference::to([
                'class' => MiddlewareDispatcher::class,
                'withMiddlewares()' => [
                    [
                        ErrorCatcher::class,
                        SessionMiddleware::class,
                        CsrfTokenMiddleware::class,
                        RequestCatcherMiddleware::class,
                        Router::class,
                    ],
                ],
            ]),
            'fallbackHandler' => Reference::to(NotFoundHandler::class),
        ],
    ],

    ParametersResolverInterface::class => [
        'class' => CompositeParametersResolver::class,
        '__construct()' => [
            Reference::to(HydratorAttributeParametersResolver::class),
            Reference::to(RequestInputParametersResolver::class),
        ],
    ],
];
```

Este arquivo é o coração da aplicação web. Ele define:

- A pilha global de middlewares, na ordem em que eles rodam. O último é o `Router`, que procura a rota e executa a action. O capítulo [Middleware](/estrutura/middleware) detalha cada um.
- O fallback handler: o `NotFoundHandler` do próprio template.
- Os resolvers de parâmetros, que permitem às actions receber valores da rota, da query e do corpo como argumentos. Eles aparecem em [Actions e controllers](/estrutura/actions-e-controllers).

O `eventDispatcher` não aparece porque o container resolve sozinho, pelo tipo `EventDispatcherInterface`.

## O fallback handler (404)

O `Router` é um middleware como os outros. Quando a URL não casa com nenhuma rota, ele não gera um 404 por conta própria: passa a requisição adiante, chamando `$handler->handle($request)`. Como ele é o último da pilha, o próximo é o fallback handler.

Sem configuração, a `Application` usa o `UnhandledRequestHandler`, que lança `RuntimeException('No response was generated.')`. Por isso o template troca pelo seu `NotFoundHandler`, em `src/Web/NotFound/NotFoundHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\NotFound;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class NotFoundHandler implements RequestHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private CurrentRoute $currentRoute,
        private WebViewRenderer $viewRenderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewRenderer
            ->render(__DIR__ . '/template', ['urlGenerator' => $this->urlGenerator, 'currentRoute' => $this->currentRoute])
            ->withStatus(Status::NOT_FOUND);
    }
}
```

É um `RequestHandlerInterface` comum, que renderiza uma view e muda o status para 404. Acessando `/estrutura/nao-existe`, a página mostra o layout do template com "The page /estrutura/nao-existe not found." e status 404.

Como é uma classe normal, uma action também pode usá-la quando um registro não existe. O capítulo [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados) faz isso com páginas fora do intervalo, e o `ArtigoController` de [Actions e controllers](/estrutura/actions-e-controllers) faz o mesmo com um slug desconhecido.

Dois casos parecidos não passam pelo fallback:

- A URL existe, mas com outro método HTTP. Um GET numa rota só de POST recebe `405 Method Not Allowed` direto do `Router`, com o header `Allow: POST`.
- Uma exceção não tratada. Quem responde é o `ErrorCatcher`, o primeiro middleware da pilha. Veja [Tratamento de erros](/requisicoes/tratamento-de-erros).

## Parâmetros da aplicação

Parâmetros são valores de configuração que não são serviços: o nome do site, um e-mail de contato, o tamanho de uma página. Eles ficam no grupo `params` da configuração, que é um array juntado a partir dos pacotes, da aplicação e do ambiente.

### Os parâmetros do template

O template guarda nome, charset e idioma em `config/common/application.php`:

```php
<?php

declare(strict_types=1);

return [
    'charset' => 'UTF-8',
    'locale' => 'en',
    'name' => 'My Project',
];
```

O `config/common/params.php` inclui esse arquivo na chave `application`. Mas as classes não recebem o array `$params`. O template cria uma classe para esses valores, `App\Shared\ApplicationParams`:

```php
<?php

declare(strict_types=1);

namespace App\Shared;

final readonly class ApplicationParams
{
    public function __construct(
        public string $name = 'My Project',
        public string $charset = 'UTF-8',
        public string $locale = 'en',
    ) {}
}
```

E a liga aos parâmetros em `config/common/di/application.php`:

```php
<?php

declare(strict_types=1);

use App\Shared\ApplicationParams;

/** @var array $params */

return [
    ApplicationParams::class => [
        '__construct()' => [
            'name' => $params['application']['name'],
            'charset' => $params['application']['charset'],
            'locale' => $params['application']['locale'],
        ],
    ],
];
```

Todo arquivo de definições de DI recebe a variável `$params` com os parâmetros já juntados. A partir daí, quem precisa do nome do site pede `ApplicationParams` no construtor. O layout do template também recebe essa classe, como a variável `$applicationParams`, e a usa no `<title>` e no rodapé.

### Os seus parâmetros

Faça o mesmo para os seus valores. Primeiro, os parâmetros, em `config/common/params/estrutura.php`:

```php
<?php

declare(strict_types=1);

return [
    'estrutura' => [
        'loja' => [
            'nome' => 'Loja do Guia',
            'email' => 'contato@loja.test',
        ],
        'chaveDoPainel' => 'chave-de-exemplo',
    ],
];
```

A chave `chaveDoPainel` é usada no capítulo [Middleware](/estrutura/middleware). Use uma chave de primeiro nível com o nome do seu módulo ou pacote, como os pacotes fazem com `yiisoft/view` ou `yiisoft/aliases`. Isso evita colisão com os parâmetros de outros pacotes.

Depois, uma classe com os valores, em `src/Estrutura/LojaParams.php`:

```php
<?php

declare(strict_types=1);

namespace App\Estrutura;

final readonly class LojaParams
{
    public function __construct(
        public string $nome,
        public string $email,
    ) {}
}
```

E a definição no container, em `config/common/di/estrutura.php`. O arquivo completo tem mais uma definição, vista no capítulo [Domínio](/estrutura/dominio); a parte que importa aqui é esta:

```php
LojaParams::class => [
    '__construct()' => [
        'nome' => $params['estrutura']['loja']['nome'],
        'email' => $params['estrutura']['loja']['email'],
    ],
],
```

Agora qualquer classe pode receber `LojaParams`. Uma action de contato, em `src/Web/Estrutura/Contato/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Contato;

use App\Estrutura\LojaParams;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LojaParams $loja,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $response->getBody()->write("Fale com a {$this->loja->nome}: {$this->loja->email}\n");

        return $response;
    }
}
```

Com a rota:

```php
Route::get('/estrutura/contato')
    ->action(Estrutura\Contato\Action::class)
    ->name('estrutura/contato'),
```

Acessando `/estrutura/contato`:

```plaintext
Fale com a Loja do Guia: contato@loja.test
```

Por que não injetar o array `$params` direto na action? Porque uma classe com propriedades tipadas deixa claro do que a action depende, o editor completa os nomes, e um erro de digitação na chave aparece na hora de montar o container, não no meio de uma página.

### Parâmetros por ambiente

Um valor que muda entre desenvolvimento e produção vai em `config/environments/<ambiente>/params.php`. Esses arquivos são juntados por último, então sobrescrevem os parâmetros de `config/common/`. O template usa isso para o `traceLink` do ambiente `dev`, que transforma os caminhos de arquivo da página de erro em links para o PhpStorm. O mecanismo completo está em [Configuração](/conceitos/configuracao).

## Comparando com o Yii2

- `Yii::$app` não existe. Em vez de `Yii::$app->params['adminEmail']`, a classe recebe um objeto de parâmetros pelo construtor.
- Os componentes da aplicação (`'components' => [...]`) viraram definições no container.
- O `catchAll` e a action de erro do `errorHandler` foram substituídos pelo fallback handler e pelo `ErrorCatcher`.
- Os eventos `beforeRequest` e `afterRequest` continuam existindo, agora como classes de evento PSR-14.
