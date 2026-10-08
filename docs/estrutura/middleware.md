# Middleware

Algumas tarefas valem para muitas rotas: iniciar a sessão, conferir o token CSRF, exigir login, medir o tempo de resposta, acrescentar headers. Repetir isso em cada action não escala. No Yii3, essas tarefas são middlewares: classes que ficam entre a requisição e a action e podem agir antes, depois ou no lugar dela. Este capítulo mostra como eles funcionam, onde ficam configurados e como escrever o seu.

## PSR-15 na prática

Os middlewares do Yii3 seguem a PSR-15. São duas interfaces:

```php
interface MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}

interface RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
```

O middleware recebe a requisição e um `$handler`, que representa todo o resto da cadeia. Ele pode:

- Fazer algo antes, chamar `$handler->handle($request)` e devolver a resposta.
- Chamar o `$handler` e alterar a resposta que voltou.
- Não chamar o `$handler` e devolver uma resposta própria, interrompendo a cadeia.

Um middleware que mede quanto tempo o resto da cadeia levou, em `src/Web/Estrutura/Middleware/TempoDeRespostaMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TempoDeRespostaMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $inicio = microtime(true);

        $response = $handler->handle($request);

        $milissegundos = (microtime(true) - $inicio) * 1000;

        return $response->withHeader('X-Tempo-Resposta', sprintf('%.2f ms', $milissegundos));
    }
}
```

Tudo antes do `$handler->handle()` roda na ida; tudo depois, na volta. Como a resposta PSR-7 é imutável, o `withHeader()` devolve uma resposta nova, e é ela que precisa ser retornada.

Esse middleware mede só o que vem depois dele. Para medir desde o começo do script, use o atributo `applicationStartTime`, que o runner coloca na requisição (veja [Script de entrada](/estrutura/script-de-entrada)).

## A pilha global e a pilha por rota

Existem dois lugares para registrar middlewares.

### A pilha global

Ela roda em toda requisição, inclusive nas que terminam em 404. Fica na definição da `Application`, em `config/web/di/application.php`:

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

A ordem é a de execução na ida:

- `ErrorCatcher` vem primeiro para capturar exceções de qualquer middleware ou action e transformá-las numa página de erro. Veja [Tratamento de erros](/requisicoes/tratamento-de-erros).
- `SessionMiddleware` abre a sessão e grava o cookie na volta. Veja [Sessões e cookies](/requisicoes/sessoes-e-cookies).
- `CsrfTokenMiddleware` recusa com 422 qualquer requisição sem token válido, exceto GET, HEAD e OPTIONS. Ele depende da sessão, por isso vem depois dela.
- `RequestCatcherMiddleware` guarda a requisição atual no `RequestProviderInterface`, para serviços que precisam dela fora da action, como os resolvers de `#[Query]` e `#[Body]`.
- `Router` encontra a rota e executa a pilha dela. Se nada casar, passa para o fallback handler.

A ordem tem efeitos visíveis. Como o CSRF vem antes do roteamento, um `DELETE /estrutura/contato` recebe 422 do `CsrfTokenMiddleware`, e não 405 do `Router`: a requisição nem chega a ser roteada.

O app de exemplo deste guia muda uma só coisa nessa pilha: no lugar do `CsrfTokenMiddleware` entra um middleware que aplica o mesmo CSRF a todo o site, menos às rotas `/api`, que são autenticadas por token. O motivo está em [APIs REST](/api/inicio-rapido#o-csrf-e-a-api). No seu projeto, para um middleware que vale para tudo (headers de segurança, por exemplo), é aqui que ele entra.

### A pilha da rota

Uma rota pode ter middlewares próprios, que rodam depois do `Router` e antes da action. A action é, na verdade, o último middleware da rota: por isso o método se chama `action()` e precisa vir depois de `middleware()`.

Um grupo aplica middlewares a todas as suas rotas. O exemplo abaixo cria um painel em `/estrutura/painel`, com o middleware de tempo e um middleware que exige uma chave (visto na próxima seção):

```php
Group::create('/estrutura/painel')
    ->middleware(TempoDeRespostaMiddleware::class, ExigeChaveMiddleware::class)
    ->routes(
        Route::get('')
            ->action(Estrutura\Painel\Action::class)
            ->name('estrutura/painel'),
        Route::get('/status')
            ->action(Estrutura\Painel\StatusAction::class)
            ->disableMiddleware(ExigeChaveMiddleware::class)
            ->name('estrutura/painel/status'),
    ),
```

Os métodos disponíveis em `Route` e em `Group`:

- `middleware(...)` acrescenta middlewares ao fim da lista. Numa `Route`, precisa vir antes de `action()`; num `Group`, antes de `routes()`.
- `prependMiddleware(...)` coloca middlewares no começo da lista.
- `disableMiddleware(...)` tira um middleware herdado do grupo. Aqui, a rota `/status` fica pública.

As duas actions do painel só escrevem um texto. A principal, em `src/Web/Estrutura/Painel/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Painel;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Bem-vindo ao painel.\n");

        return $response;
    }
}
```

A `StatusAction` é igual, mas escreve `ok`.

## Escrevendo o seu middleware

O middleware que protege o painel confere o header `X-Chave`. Em `src/Web/Estrutura/Middleware/ExigeChaveMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;

final readonly class ExigeChaveMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $chave,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!hash_equals($this->chave, $request->getHeaderLine('X-Chave'))) {
            $response = $this->responseFactory->createResponse(Status::FORBIDDEN);
            $response->getBody()->write("Chave ausente ou inválida.\n");

            return $response;
        }

        return $handler->handle($request);
    }
}
```

Quando a chave não confere, ele devolve 403 sem chamar o `$handler`, e a action nunca roda. O `hash_equals()` compara as strings em tempo constante, o que é o certo para segredos.

O construtor pede uma string, que o autowiring não tem como adivinhar. O valor vem dos parâmetros (`chaveDoPainel`, definido no capítulo [Aplicação](/estrutura/aplicacao)) por uma definição no container. Como middleware só existe na web, ela fica em `config/web/di/estrutura.php`:

```php
<?php

declare(strict_types=1);

use App\Web\Estrutura\Middleware\ExigeChaveMiddleware;

/** @var array $params */

return [
    ExigeChaveMiddleware::class => [
        '__construct()' => [
            'chave' => $params['estrutura']['chaveDoPainel'],
        ],
    ],
];
```

O `ResponseFactoryInterface` continua vindo por autowiring; a definição só informa o argumento `chave`.

### Testando

Sem a chave:

```bash
curl -i http://127.0.0.1:8080/estrutura/painel
```

```plaintext
HTTP/1.1 403 Forbidden
Host: 127.0.0.1:8080
Date: Thu, 08 Oct 2026 05:08:23 GMT
Connection: close
X-Tempo-Resposta: 0.30 ms
Content-Length: 28
Content-type: text/html; charset=UTF-8

Chave ausente ou inválida.
```

O header `X-Tempo-Resposta` aparece mesmo com o 403. O `TempoDeRespostaMiddleware` vem antes na lista, então ele envolve o `ExigeChaveMiddleware` e recebe a resposta dele na volta.

Com a chave:

```bash
curl -i -H "X-Chave: chave-de-exemplo" http://127.0.0.1:8080/estrutura/painel
```

```plaintext
HTTP/1.1 200 OK
Host: 127.0.0.1:8080
Date: Thu, 08 Oct 2026 05:08:23 GMT
Connection: close
X-Tempo-Resposta: 0.47 ms
Content-Length: 21
Content-type: text/html; charset=UTF-8

Bem-vindo ao painel.
```

As actions do painel não definem `Content-Type`, então vale o padrão do PHP, `text/html`.

E `/estrutura/painel/status`, sem chave nenhuma, responde `200` com `ok`, porque a rota desligou o `ExigeChaveMiddleware`. O header de tempo continua lá. Os valores de tempo mudam a cada requisição.

### Outras formas de declarar um middleware

O `MiddlewareFactory` aceita mais do que o nome de uma classe que implementa `MiddlewareInterface`:

- Uma classe com `__invoke()` ou um par `[classe, método]`, como as actions. Se o retorno for um `ResponseInterface`, ele é usado como resposta.
- Uma closure, que recebe a requisição e o handler como argumentos.
- Uma definição em array, como `['class' => MeuMiddleware::class, '__construct()' => ['chave' => 'abc']]`, para configurar o middleware direto na rota.

Para passar dados de um middleware para a action, use atributos da requisição: o middleware chama `$handler->handle($request->withAttribute('usuario', $usuario))`, e a action lê com `$request->getAttribute('usuario')` ou com o atributo `#[Request('usuario')]`.

::: tip
Antes de escrever um middleware, procure nos pacotes. O `yiisoft/yii-middleware` traz middlewares prontos, como `ForceSecureConnection` (redireciona para HTTPS), `Locale`, `IpFilter` e `HttpCache`. Para autenticação, o `yiisoft/auth` tem o middleware `Authentication`, e o `yiisoft/user` tem o `LoginMiddleware`. Veja [Autenticação](/seguranca/autenticacao).
:::

## Comparando com o Yii2

- No Yii2, o que acontecia antes ou depois de uma action ficava em `behaviors()` e em filtros (`AccessControl`, `VerbFilter`, `ContentNegotiator`). No Yii3, tudo isso é middleware.
- Os filtros do Yii2 eram ligados a um controller. Os middlewares do Yii3 são ligados a rotas e grupos, ou à aplicação inteira.
- O `beforeAction()` que devolvia `false` para interromper a action equivale a um middleware que devolve uma resposta sem chamar o `$handler`.
- Os middlewares seguem uma PSR, então middlewares PSR-15 de outros projetos também funcionam.
