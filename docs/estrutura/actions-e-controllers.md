# Actions e controllers

A action é o código que responde a uma rota: lê o que veio na requisição, chama o domínio e devolve uma resposta. No Yii3 não existe uma classe base de controller para estender. Uma action pode ser uma classe com `__invoke()`, um método de um controller com vários métodos ou até uma closure. Este capítulo mostra as duas formas mais comuns e como os valores da rota, da query e do corpo chegam como argumentos.

## Action de uma classe só, com __invoke()

O template usa uma classe por página. A página inicial, em `src/Web/HomePage/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\HomePage;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template');
    }
}
```

E a rota aponta para a classe:

```php
Route::get('/')
    ->action(Web\HomePage\Action::class)
    ->name('home'),
```

Não há nada de especial na classe: ela não estende nada e não implementa interface nenhuma. O que a torna uma action é o que acontece quando a rota casa. O `Router` entrega a definição `Web\HomePage\Action::class` ao `MiddlewareFactory` (do pacote `yiisoft/middleware-dispatcher`), que:

1. Vê que a classe tem `__invoke()`.
2. Pede a instância ao container. O construtor é resolvido por autowiring, como qualquer serviço (veja [Container de DI](/conceitos/container-di)).
3. Chama `__invoke()`, preenchendo os argumentos.
4. Exige que o retorno seja um `ResponseInterface`.

Os argumentos do método são preenchidos em três etapas:

- Um argumento do tipo `ServerRequestInterface` recebe a requisição, e um do tipo `RequestHandlerInterface` recebe o próximo handler da cadeia (na prática, o fallback handler de 404).
- Argumentos com atributos como `#[Query]` e `#[RouteArgument]` são resolvidos pelos resolvers configurados em `ParametersResolverInterface`, vistos mais abaixo.
- O que sobrar é resolvido pelo container, pelo tipo. Uma action pode pedir um serviço direto no `__invoke()`, sem passar pelo construtor.

Os capítulos anteriores desta seção já usaram esse formato: a action de [ambiente](/estrutura/script-de-entrada) e a de [contato](/estrutura/aplicacao) são classes de um método só.

Essa é a forma recomendada pelo template. Cada action recebe só as dependências que usa, o arquivo é pequeno e o nome da pasta já diz o que ela faz.

## Controllers com vários métodos

Quando várias páginas giram em torno do mesmo recurso, você pode agrupar as actions numa classe só. A rota passa a apontar para um par `[classe, método]`.

Um controller de artigos, em `src/Web/Estrutura/Artigo/ArtigoController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Artigo;

use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class ArtigoController
{
    private const ARTIGOS = [
        'actions' => 'Actions e controllers',
        'middleware' => 'Middleware',
        'views' => 'Views',
    ];

    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ResponseFactoryInterface $responseFactory,
        private NotFoundHandler $notFoundHandler,
    ) {}

    public function listar(#[Query('busca')] string $busca = ''): ResponseInterface
    {
        $artigos = array_filter(
            self::ARTIGOS,
            static fn(string $titulo): bool => $busca === '' || mb_stripos($titulo, $busca) !== false,
        );

        return $this->viewRenderer->render(__DIR__ . '/listar', [
            'artigos' => $artigos,
            'busca' => $busca,
        ]);
    }

    public function ver(ServerRequestInterface $request, #[RouteArgument('slug')] string $slug): ResponseInterface
    {
        if (!isset(self::ARTIGOS[$slug])) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/ver', [
            'titulo' => self::ARTIGOS[$slug],
        ]);
    }

    public function comentar(
        #[RouteArgument('slug')] string $slug,
        #[Body('texto')] string $texto = '',
    ): ResponseInterface {
        $response = $this->responseFactory
            ->createResponse(Status::CREATED)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $response->getBody()->write(sprintf(
            "Comentário com %d caracteres recebido para \"%s\".\n",
            mb_strlen($texto),
            $slug,
        ));

        return $response;
    }
}
```

Para manter o foco na estrutura, os artigos estão numa constante. Num app real eles viriam de um repositório, como no capítulo [Domínio](/estrutura/dominio).

As rotas:

```php
Route::get('/estrutura/artigos')
    ->action([ArtigoController::class, 'listar'])
    ->name('estrutura/artigos'),
Route::get('/estrutura/artigos/{slug}')
    ->action([ArtigoController::class, 'ver'])
    ->name('estrutura/artigo'),
Route::post('/estrutura/artigos/{slug}/comentarios')
    ->action([ArtigoController::class, 'comentar'])
    ->name('estrutura/artigo/comentar'),
```

A view da lista, em `src/Web/Estrutura/Artigo/listar.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var array<string, string> $artigos
 * @var string $busca
 */

$this->setTitle('Artigos');
?>

<h1>Artigos</h1>

<?php if ($busca !== ''): ?>
    <p>Resultados para "<?= Html::encode($busca) ?>":</p>
<?php endif ?>

<ul>
    <?php foreach ($artigos as $slug => $titulo): ?>
        <li>
            <a href="<?= $urlGenerator->generate('estrutura/artigo', ['slug' => $slug]) ?>"><?= Html::encode($titulo) ?></a>
        </li>
    <?php endforeach ?>
</ul>
```

E a do artigo, em `src/Web/Estrutura/Artigo/ver.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $titulo
 */

$this->setTitle($titulo);
?>

<h1><?= Html::encode($titulo) ?></h1>
```

O mecanismo é o mesmo da action com `__invoke()`: o controller vem do container e os argumentos do método são resolvidos da mesma forma. A diferença é que o construtor recebe a soma das dependências de todos os métodos. Aqui, `listar()` não usa o `ResponseFactoryInterface`, mas recebe mesmo assim.

::: tip Sem estado no controller
O container guarda uma instância só de cada classe. Num servidor de longa duração, como o RoadRunner, o mesmo controller atende várias requisições. Não guarde dados da requisição em propriedades; deixar a classe `readonly`, como acima, evita isso. Mais em [Imutabilidade](/conceitos/imutabilidade).
:::

### Qual usar

As duas formas convivem no mesmo app. Uma regra prática: comece com uma classe por action. Se várias actions do mesmo recurso compartilham quase todas as dependências e são curtas, junte-as num controller. Se um controller começa a pedir muitas dependências que só um método usa, separe.

## Parâmetros vindos da rota, da query e do corpo

O `ArtigoController` usa os três atributos mais comuns:

| Atributo | De onde vem o valor | Pacote |
|---|---|---|
| `#[RouteArgument('slug')]` | Do trecho `{slug}` da rota | `yiisoft/router` |
| `#[Query('busca')]` | Da query string, `?busca=...` | `yiisoft/input-http` |
| `#[Body('texto')]` | Do corpo já interpretado da requisição (`getParsedBody()`) | `yiisoft/input-http` |

Sem o nome entre parênteses, o atributo usa o nome do argumento. `#[Query] string $busca` é o mesmo que `#[Query('busca')] string $busca`.

Quando o valor não existe na requisição, o argumento fica com o valor padrão. Se não houver valor padrão, a chamada falha com erro 500, então dê um padrão para tudo que é opcional.

O valor é convertido para o tipo do argumento. No capítulo [Domínio](/estrutura/dominio), a rota `/estrutura/tarefas/{id:\d+}/concluir` entrega o `id` como string, e a action o recebe como `int $id`.

Testando a lista e a busca:

- `/estrutura/artigos` lista os três artigos, com links como `/estrutura/artigos/middleware`.
- `/estrutura/artigos?busca=VIEW` mostra "Resultados para "VIEW":" e só o artigo "Views". O `mb_stripos()` ignora maiúsculas.
- `/estrutura/artigos/middleware` mostra o título "Middleware".
- `/estrutura/artigos/nao-existe` devolve a página 404 do template.

Para o POST, lembre que o `CsrfTokenMiddleware` está na pilha global. Sem o token, a resposta é `422 Unprocessable Entity`, antes de chegar no controller. O token pode ir no campo `_csrf` ou no header `X-CSRF-Token`, junto com o cookie da sessão. Com o curl, primeiro pegue o token na meta tag `csrf` que o layout gera, guardando o cookie; depois envie o comentário:

```bash
TOKEN=$(curl -s -c cookies.txt http://127.0.0.1:8080/estrutura/artigos | grep -o 'name="csrf" content="[^"]*"' | sed 's/.*content="//;s/"$//')
curl -i -b cookies.txt -H "X-CSRF-Token: $TOKEN" -d "texto=Gostei" http://127.0.0.1:8080/estrutura/artigos/views/comentarios
```

```plaintext
HTTP/1.1 201 Created
Host: 127.0.0.1:8080
Date: Thu, 08 Oct 2026 05:07:25 GMT
Connection: close
Content-Type: text/plain; charset=utf-8
Content-Length: 52

Comentário com 6 caracteres recebido para "views".
```

Num formulário HTML, o `->csrf($csrf)` cuida do token, como em [Trabalhando com formulários](/primeiros-passos/formularios).

E um GET em `/estrutura/artigos/views/comentarios` devolve `405 Method Not Allowed`, porque a rota só aceita POST.

### Outras fontes

Os atributos não são a única forma de ler a requisição:

- Pedindo `ServerRequestInterface $request`, você lê tudo direto: `$request->getQueryParams()`, `$request->getParsedBody()`, `$request->getHeaderLine('Accept')`.
- O serviço `Yiisoft\Router\CurrentRoute` tem `getArgument('slug')`, `getName()` e `getUri()` da rota atual.
- O `yiisoft/input-http` também tem `#[Request]` (atributos da requisição) e `#[UploadedFiles]`.
- Uma classe que implementa `RequestInputInterface` pode ser recebida inteira como argumento, já preenchida com os dados da requisição.

Esses recursos estão em [Requisição](/requisicoes/requisicao). Para formulários, o caminho é o `FormHydrator`, visto em [Trabalhando com formulários](/primeiros-passos/formularios).

## Comparando com o Yii2

- No Yii2, todo controller estendia `yii\web\Controller`, e as actions eram métodos `actionIndex()`, `actionView()`. No Yii3, o controller é uma classe comum, os métodos têm qualquer nome e a rota diz qual chamar.
- A rota `controller/action` deixou de ser automática. Cada URL precisa de uma rota declarada.
- No Yii2, os argumentos de `actionView($id)` vinham da query string sem atributo nenhum. No Yii3, você declara a origem com `#[Query]`, `#[RouteArgument]` ou `#[Body]`.
- `$this->render()` e `$this->redirect()` não existem mais. A action recebe o `WebViewRenderer` ou o `ResponseFactoryInterface` e monta a resposta.
- `behaviors()` e filtros como `AccessControl` deram lugar aos middlewares, vistos no [próximo capítulo](/estrutura/middleware).
