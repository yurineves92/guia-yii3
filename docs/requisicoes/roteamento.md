# Roteamento e URLs

No Yii3, nenhuma URL funciona por convenção. Cada endereço que a aplicação atende precisa de uma rota declarada, que liga um método HTTP e um padrão de URL a uma action. Este capítulo mostra como declarar rotas, agrupá-las, capturar partes da URL e gerar links a partir do nome da rota.

O roteamento vem de dois pacotes: `yiisoft/router`, com as classes `Route`, `Group` e as interfaces, e `yiisoft/router-fastroute`, o adaptador que faz a comparação de fato usando o [FastRoute](https://github.com/nikic/FastRoute). O template já traz os dois configurados.

## Route

As rotas ficam no arquivo de rotas, `config/common/routes.php`, que devolve um array. Uma rota é criada com um método estático que indica o verbo HTTP:

```php
Route::get('/ola')
    ->action(Web\Ola\Action::class)
    ->name('ola'),
```

Existem `get()`, `post()`, `put()`, `delete()`, `patch()`, `head()` e `options()`. Para aceitar mais de um verbo, use `methods()`:

```php
Route::methods([Method::GET, Method::POST], '/cadastro')
    ->action(Web\Cadastro\Action::class)
    ->name('cadastro'),
```

O `action()` recebe o que vai tratar a requisição. Normalmente é o nome de uma classe com `__invoke()`, mas também pode ser `[Classe::class, 'metodo']` ou uma closure. O capítulo [Actions e controllers](/estrutura/actions-e-controllers) mostra essas variações.

O `name()` é opcional, mas sem ele você não consegue gerar a URL da rota pelo nome. Rotas sem nome recebem um nome automático, como `GET /ola`. Os nomes precisam ser únicos: se duas rotas tiverem o mesmo nome, o router lança `InvalidArgumentException` com a mensagem `A route with name '...' already exists.`

Todos os métodos de `Route` devolvem uma cópia nova do objeto, por isso a configuração é encadeada. Há uma regra de ordem: `middleware()` precisa vir antes de `action()`, e o router lança uma exceção se você inverter.

Quando o caminho existe mas o verbo não confere, o router responde `405 Method Not Allowed` com o header `Allow`. Para `OPTIONS`, ele responde sozinho com `204` e o mesmo header:

```bash
curl -i -X OPTIONS http://127.0.0.1:8080/requisicoes/links
```

```plaintext
HTTP/1.1 204 No Content
Allow: GET
```

## Group

Um `Group` junta rotas que compartilham um prefixo de URL, um prefixo de nome ou middlewares. Os exemplos desta seção do guia ficam todos sob `/requisicoes`. Este é o trecho do arquivo de rotas com as rotas deste capítulo:

```php
use App\Web\Requisicoes;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/requisicoes')
        ->namePrefix('requisicoes/')
        ->routes(
            Route::get('/artigos/{id:\d+}')
                ->action(Requisicoes\Artigo\Action::class)
                ->name('artigo'),
            Route::get('/arquivo[/{ano:\d{4}}[/{mes:\d{1,2}}]]')
                ->action(Requisicoes\Arquivo\Action::class)
                ->defaults(['ano' => 2026])
                ->name('arquivo'),
            Route::get('/links')
                ->action(Requisicoes\Links\Action::class)
                ->name('links'),
        ),
];
```

- `Group::create('/requisicoes')` prefixa o caminho de todas as rotas do grupo. A primeira rota atende `/requisicoes/artigos/42`.
- `namePrefix('requisicoes/')` prefixa os nomes. A rota `artigo` passa a se chamar `requisicoes/artigo`. Isso evita colisão de nomes entre partes diferentes da aplicação.
- Grupos podem conter outros grupos, e os prefixos se acumulam.

O grupo também aceita `middleware()`, aplicado a todas as rotas de dentro, e `host()`, para restringir as rotas a um domínio. Middleware tem um capítulo próprio: veja [Middleware](/estrutura/middleware).

## Parâmetros na URL

Um trecho entre chaves vira um parâmetro: `{id}`. Depois dos dois pontos vem uma expressão regular que o valor precisa satisfazer. Em `{id:\d+}`, só dígitos são aceitos, então `/requisicoes/artigos/abc` não casa com a rota e cai no 404 do template.

Sem expressão, o parâmetro aceita qualquer coisa até a próxima barra (`[^/]+`).

Na action, a forma mais direta de receber o valor é o atributo `#[RouteArgument]`. Crie `src/Web/Requisicoes/Artigo/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Artigo;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(#[RouteArgument('id')] int $id): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Artigo número $id");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

O valor chega da URL como string e é convertido para `int` por causa do tipo do parâmetro. Se o nome do parâmetro do método for igual ao da rota, dá para omitir o nome: `#[RouteArgument] int $id`.

A resposta em texto puro é montada com `ResponseFactoryInterface`. O capítulo [Resposta](/requisicoes/resposta) explica essa parte.

### Partes opcionais e valores padrão

Colchetes marcam uma parte opcional do padrão. Em `/arquivo[/{ano:\d{4}}[/{mes:\d{1,2}}]]`, a rota atende `/requisicoes/arquivo`, `/requisicoes/arquivo/2024` e `/requisicoes/arquivo/2024/3`. Partes opcionais só podem ficar no final do padrão, e podem ser aninhadas como no exemplo.

O `defaults(['ano' => 2026])` dá um valor ao parâmetro quando ele não está na URL.

Outra forma de ler os parâmetros é pelo serviço `CurrentRoute`, que guarda a rota que casou com a requisição atual. Crie `src/Web/Requisicoes/Arquivo/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Arquivo;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(CurrentRoute $currentRoute): ResponseInterface
    {
        $ano = $currentRoute->getArgument('ano');
        $mes = $currentRoute->getArgument('mes', 'todos');

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(
            "Rota: {$currentRoute->getName()}\n"
            . "Ano: $ano\n"
            . "Mês: $mes\n",
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

O `getArgument()` aceita um segundo parâmetro com o valor usado quando o argumento não existe. O `CurrentRoute` também informa o nome da rota (`getName()`), o padrão (`getPattern()`) e a URI (`getUri()`).

Testando:

```bash
curl http://127.0.0.1:8080/requisicoes/arquivo
curl http://127.0.0.1:8080/requisicoes/arquivo/2024/3
```

```plaintext
Rota: requisicoes/arquivo
Ano: 2026
Mês: todos

Rota: requisicoes/arquivo
Ano: 2024
Mês: 3
```

Na primeira chamada, o ano veio do `defaults()`.

## Gerando URLs

Escrever caminhos na mão espalha a estrutura das URLs pelo código. O `UrlGeneratorInterface` monta a URL a partir do nome da rota. Ele já está disponível nas views como `$urlGenerator`, e em qualquer classe pelo construtor. Crie `src/Web/Requisicoes/Links/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Links;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $links = [
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42]),
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42], ['aba' => 'comentarios']),
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42, 'destaque' => 1]),
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42], hash: 'fim'),
            $this->urlGenerator->generate('requisicoes/arquivo'),
            $this->urlGenerator->generate('requisicoes/arquivo', ['ano' => 2024, 'mes' => 3]),
            $this->urlGenerator->generateAbsolute('requisicoes/artigo', ['id' => 42]),
        ];

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(implode("\n", $links) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Acessando `/requisicoes/links`:

```plaintext
/requisicoes/artigos/42
/requisicoes/artigos/42?aba=comentarios
/requisicoes/artigos/42?destaque=1
/requisicoes/artigos/42#fim
/requisicoes/arquivo
/requisicoes/arquivo/2024/3
http://127.0.0.1:8080/requisicoes/artigos/42
```

Linha por linha:

1. O `generate()` recebe o nome da rota e os argumentos do padrão.
2. O terceiro parâmetro vira a query string.
3. Argumentos que não existem no padrão também vão para a query string.
4. O `hash` adiciona o fragmento depois do `#`.
5. Sem argumentos, as partes opcionais ficam de fora. O valor de `defaults()` vale para a comparação da URL, não para a geração.
6. Com os argumentos, as partes opcionais aparecem.
7. O `generateAbsolute()` inclui esquema e host. Por padrão ele usa os da requisição atual; os parâmetros `scheme` e `host` permitem trocar.

Se faltar um argumento obrigatório, o `generate()` lança uma exceção dizendo quais argumentos a rota espera. Se o nome não existir, lança `RouteNotFoundException`.

Também existe o `generateFromCurrent()`, que gera a URL da rota atual trocando só alguns argumentos. É útil em links de paginação e filtros.

## Rotas em vários arquivos

O template usa um único `config/common/routes.php`. Num projeto maior, dá para dividir as rotas por área, e o app de exemplo deste guia faz isso: cada seção tem o seu arquivo em `config/common/routes/`. O capítulo [Configuração](/conceitos/configuracao) mostra como isso foi ligado. Com vários arquivos, o `namePrefix()` no grupo de cada área ajuda a manter os nomes únicos.

## Comparando com o Yii2

- No Yii2, o `UrlManager` com `enablePrettyUrl` deduzia a rota `controller/action` da URL, mesmo sem regra. No Yii3 não existe rota implícita: o que não estiver declarado dá 404.
- As regras do Yii2 (`'artigos/<id:\d+>' => 'artigo/view'`) viram objetos `Route`, com o verbo HTTP explícito.
- O `Url::to(['artigo/view', 'id' => 42])` vira `$urlGenerator->generate('requisicoes/artigo', ['id' => 42])`, sempre pelo nome da rota.
- Os parâmetros não são passados para a action pelo nome automaticamente. Você usa `#[RouteArgument]` ou o `CurrentRoute`.
