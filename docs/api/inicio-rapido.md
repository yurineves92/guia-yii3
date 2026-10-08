# Início rápido

Uma API REST recebe e devolve JSON, não tem páginas nem formulários e é usada por outros programas: um app de celular, um front-end separado, outro sistema. No Yii3, isso não exige um framework diferente: são as mesmas rotas, actions e middlewares, com outras respostas. Este capítulo apresenta o template próprio para APIs e cria os dois primeiros endpoints dentro do app de exemplo: um que responde JSON e outro que recebe JSON.

## O template yiisoft/app-api

Se o projeto vai ser só uma API, comece pelo template `yiisoft/app-api`:

```bash
composer create-project yiisoft/app-api minha-api
cd minha-api
cp .env.example .env
./yii serve
```

A estrutura é parecida com a do `yiisoft/app` (`config/`, `src/`, `public/`, `runtime/`), mas sem o que só faz sentido em sites: não há views, layouts, assets, sessão nem proteção CSRF. No lugar, ele já traz os pacotes de API, como o `yiisoft/data-response` (respostas a partir de dados), o `yiisoft/request-body-parser` (leitura de corpo JSON), o `yiisoft/input-http` e o `yiisoft/validator`. A pilha de middlewares do template faz negociação de conteúdo (JSON por padrão, XML se o cliente pedir no `Accept`), captura erros, lê o corpo da requisição, roteia e responde 404 quando nenhuma rota casa. O código de exemplo fica em `src/Api/`, com uma action inicial que devolve o nome e a versão da aplicação.

Nesta seção, não vamos usar o template. Os exemplos são montados peça por peça dentro do app de exemplo do guia, que nasceu do `yiisoft/app`. Assim você vê o que cada pacote faz e consegue acrescentar uma API a um site que já existe. Se for começar pelo `yiisoft/app-api`, várias dessas peças já estarão prontas.

## Primeiro endpoint JSON

Para transformar dados em resposta JSON, usamos o `JsonResponseFactory`, do pacote `yiisoft/data-response`. No `yiisoft/app`, o pacote já vem instalado como dependência do `yiisoft/yii-view-renderer`. Se não estiver no seu projeto:

```bash
composer require yiisoft/data-response
```

Crie `src/Web/Api/Ping/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Ping;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->responseFactory->createResponse([
            'status' => 'ok',
            'hora' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
```

O `createResponse()` recebe os dados, o status (200 por padrão) e devolve uma resposta PSR-7 com `Content-Type: application/json; charset=UTF-8`. O corpo é um `DataStream`: os dados só viram texto quando o corpo é lido, na hora de enviar a resposta. O `JsonFormatter` usado por baixo grava com `JSON_UNESCAPED_SLASHES` e `JSON_UNESCAPED_UNICODE`, então barras e acentos saem legíveis.

As rotas da API ficam num grupo com o prefixo `/api`, no arquivo de rotas:

```php
use App\Web\Api;
use Yiisoft\Request\Body\RequestBodyParser;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

Group::create('/api')
    ->namePrefix('api/')
    ->middleware(RequestBodyParser::class)
    ->routes(
        Route::get('/ping')
            ->action(Api\Ping\Action::class)
            ->name('ping'),
        Route::post('/eco')
            ->action(Api\Eco\Action::class)
            ->name('eco'),
    ),
```

A segunda rota e o `RequestBodyParser` aparecem na próxima seção. No capítulo [Erros](/api/erros), a lista de middlewares do grupo ganha um tratamento de erros próprio para a API.

```bash
curl -i http://127.0.0.1:8080/api/ping
```

```plaintext
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8
Content-Length: 50

{"status":"ok","hora":"2026-10-08T02:06:54-03:00"}
```

## Recebendo JSON

O PHP só preenche o `$_POST` para formulários (`application/x-www-form-urlencoded` e `multipart/form-data`). Um corpo JSON chega como texto puro. Quem o transforma em array é o middleware `RequestBodyParser`, do pacote `yiisoft/request-body-parser`: quando o `Content-Type` é `application/json`, ele decodifica o corpo e o coloca em `$request->getParsedBody()`. Se o JSON for inválido, ele responde `400 Bad Request` e a action nem roda.

Como ele está no grupo `/api`, só as rotas da API pagam esse custo. Para ver o resultado, crie uma action que devolve o que recebeu, em `src/Web/Api/Eco/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Eco;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory->createResponse([
            'recebido' => $request->getParsedBody(),
        ]);
    }
}
```

E mande um JSON. Num app recém-criado a partir do `yiisoft/app`, a resposta é esta:

```bash
curl -i -X POST -H "Content-Type: application/json" -d '{"nome":"Ana","linguagens":["PHP","Go"]}' http://127.0.0.1:8080/api/eco
```

```plaintext
HTTP/1.1 422 Unprocessable Entity
Content-Length: 20
Content-type: text/html; charset=UTF-8

Unprocessable entity
```

A action não chegou a rodar. Quem respondeu foi a proteção contra CSRF.

## O CSRF e a API

O template `yiisoft/app` coloca o `CsrfTokenMiddleware` na pilha global de middlewares, em `config/web/di/application.php`:

```php
'withMiddlewares()' => [
    [
        ErrorCatcher::class,
        SessionMiddleware::class,
        CsrfTokenMiddleware::class,
        RequestCatcherMiddleware::class,
        Router::class,
    ],
],
```

Toda requisição `POST`, `PUT`, `PATCH` ou `DELETE` precisa trazer um token CSRF válido, que fica guardado na sessão. Um formulário do próprio site tem esse token; um cliente de API, não.

A primeira ideia é tirar o middleware só do grupo `/api` com `disableMiddleware()`:

```php
Group::create('/api')
    ->disableMiddleware(CsrfTokenMiddleware::class)
```

Não funciona: a resposta continua sendo `422`. O `disableMiddleware()` de rotas e grupos só remove middlewares da pilha da própria rota. O CSRF está na pilha global, que roda antes do roteador. Quando o roteador descobre que a requisição é para `/api/eco`, o CSRF já recusou.

E a API precisa mesmo de CSRF? O ataque de CSRF funciona porque o navegador envia o cookie de sessão automaticamente para o site, mesmo quando a requisição parte de outra página. Uma API autenticada por token no cabeçalho `Authorization`, como a do capítulo [Autenticação](/api/autenticacao), não depende de cookie: o navegador não acrescenta esse cabeçalho sozinho, e a página maliciosa não conhece o token. Por isso o `yiisoft/app-api` não tem CSRF.

Num projeto que começou do `yiisoft/app`, há dois jeitos de tirar a API do CSRF sem desproteger o site.

### Opção 1: CSRF só nas rotas do site

Tire o `CsrfTokenMiddleware` da pilha global e aplique-o ao grupo de rotas do site. Em `config/web/di/application.php`, remova a linha `CsrfTokenMiddleware::class` da lista. No arquivo de rotas, o template já agrupa as rotas do site num `Group::create()`; acrescente o middleware a ele:

```php
use Yiisoft\Csrf\CsrfTokenMiddleware;

return [
    Group::create()
        ->middleware(CsrfTokenMiddleware::class)
        ->routes(
            Route::get('/')
                ->action(Web\HomePage\Action::class)
                ->name('home'),
            // ... as outras rotas do site
        ),
    Group::create('/api')
        // ... as rotas da API, fora do grupo com CSRF
];
```

É o formato mais explícito, mas tem um risco: toda rota do site que recebe formulário precisa estar num grupo com o `CsrfTokenMiddleware`. Esqueça uma, e ela fica sem proteção.

### Opção 2: CSRF global, com exceção para a API

Quando as rotas do site estão espalhadas por vários arquivos, como no app de exemplo deste guia, é mais seguro manter o CSRF valendo para tudo e abrir só a exceção da API. O app de exemplo usa este middleware, em `src/Web/Api/Shared/CsrfExcetoApi.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Csrf\CsrfTokenMiddleware;

/**
 * Aplica a proteção CSRF a todo o site, menos às rotas da API.
 * A API é autenticada por token no cabeçalho Authorization, e não por cookie.
 */
final readonly class CsrfExcetoApi implements MiddlewareInterface
{
    public function __construct(
        private CsrfTokenMiddleware $csrf,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $caminho = $request->getUri()->getPath();

        if ($caminho === '/api' || str_starts_with($caminho, '/api/')) {
            return $handler->handle($request);
        }

        return $this->csrf->process($request, $handler);
    }
}
```

E, na pilha global de `config/web/di/application.php`, ele entra no lugar do `CsrfTokenMiddleware`:

```php
'withMiddlewares()' => [
    [
        ErrorCatcher::class,
        SessionMiddleware::class,
        CsrfExcetoApi::class,
        RequestCatcherMiddleware::class,
        Router::class,
    ],
],
```

O `CsrfTokenMiddleware` continua existindo no container, então os formulários e o `$csrf` das views funcionam como antes. Um POST sem token para `/cadastro` continua recebendo `422`. A exceção vale só para o caminho que começa com `/api/`, e não para algo como `/apix`.

Com isso, a mesma requisição de antes chega na action:

```plaintext
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8
Content-Length: 53

{"recebido":{"nome":"Ana","linguagens":["PHP","Go"]}}
```

O `Content-Type` faz diferença. Sem o `-H "Content-Type: application/json"`, o curl envia o corpo como formulário, o `RequestBodyParser` não age, o PHP tenta ler o JSON como campos de formulário e o resultado é este:

```plaintext
{"recebido":{"{\"nome\":\"Ana\"}":""}}
```

## Comparando com o Yii2

No Yii2, uma API costumava usar `yii\rest\Controller` ou `yii\rest\ActiveController`, que formatavam a resposta a partir do valor retornado pela action. O JSON do corpo era lido configurando o `yii\web\JsonParser` em `request.parsers`, e a validação de CSRF podia ser desligada por controller com `$enableCsrfValidation = false`. No Yii3:

- A action devolve uma resposta PSR-7. O `JsonResponseFactory` cria essa resposta a partir dos dados.
- O corpo JSON é lido pelo middleware `RequestBodyParser`, aplicado só onde for preciso.
- O CSRF é um middleware. Ele vale para as rotas onde está na pilha, e não há chave por controller para desligá-lo.
