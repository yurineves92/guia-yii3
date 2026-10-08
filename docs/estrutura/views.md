# Views

As views do Yii3 continuam sendo arquivos PHP com HTML, como no Yii2. O que mudou é quem as renderiza e como as variáveis chegam até elas. Este capítulo mostra as duas classes envolvidas, como trocar o layout de um grupo de páginas e como entregar variáveis a todas as views sem passar uma por uma na action.

## WebView e WebViewRenderer

Duas classes, de dois pacotes, dividem o trabalho:

- `Yiisoft\View\WebView`, do `yiisoft/view`, executa o arquivo PHP e devolve uma string. Ela guarda o título da página, as tags `<meta>` e `<link>`, o CSS e o JS registrados, os blocos e os parâmetros comuns. Dentro de uma view, `$this` é a `WebView`.
- `Yiisoft\Yii\View\Renderer\WebViewRenderer`, do `yiisoft/yii-view-renderer`, é o que a action usa. Ele renderiza a view, aplica o layout, acrescenta as injeções e devolve um `ResponseInterface` com `Content-Type: text/html`.

Os métodos do `WebViewRenderer` que você mais usa:

| Método | Devolve | Layout |
|---|---|---|
| `render($view, $parametros)` | `ResponseInterface` | sim |
| `renderPartial($view, $parametros)` | `ResponseInterface` | não |
| `renderAsString($view, $parametros)` | `string` | sim |
| `renderPartialAsString($view, $parametros)` | `string` | não |

O `render()` não executa a view na hora. Ele devolve uma resposta com renderização adiada, e o HTML só é gerado quando alguém lê o corpo. Na prática isso não muda nada para a action, mas explica por que um erro dentro da view aparece "depois" do `return`.

### Como o nome da view vira um arquivo

As actions do template passam o caminho absoluto sem extensão, `__DIR__ . '/template'`, e o `.php` é acrescentado. Outras formas aceitas pela `WebView`:

- `./_item` e `../_cabecalho`: relativos ao arquivo da view que está sendo renderizada. Só funcionam dentro de uma view.
- `//pasta/arquivo`: relativo ao `basePath` da `WebView`.
- `pasta/arquivo`, sem prefixo: relativo ao `viewPath` do `WebViewRenderer`. O template configura `viewPath` como `null`, então essa forma não está disponível até você configurar um, com o parâmetro `yiisoft/yii-view-renderer` ou com `withViewPath()`.

## Layouts e parâmetros comuns

O layout padrão vem dos parâmetros, em `config/common/params.php`:

```php
'yiisoft/yii-view-renderer' => [
    'viewPath' => null,
    'layout' => '@src/Web/Shared/Layout/Main/layout.php',
    'injections' => [
        Reference::to(CsrfViewInjection::class),
    ],
],
```

O `WebViewRenderer` renderiza a view primeiro e depois o layout, passando o resultado na variável `$content`.

No mesmo arquivo ficam os parâmetros comuns da `WebView`, que existem em todas as views e layouts:

```php
'yiisoft/view' => [
    'basePath' => null,
    'parameters' => [
        'assetManager' => Reference::to(AssetManager::class),
        'applicationParams' => Reference::to(ApplicationParams::class),
        'aliases' => Reference::to(Aliases::class),
        'urlGenerator' => Reference::to(UrlGeneratorInterface::class),
        'currentRoute' => Reference::to(CurrentRoute::class),
    ],
],
```

É por isso que as views usam `$urlGenerator` sem que a action o passe. Um parâmetro passado no `render()` com o mesmo nome tem prioridade sobre o comum.

### Um layout próprio

Uma seção do site pode ter outro layout. Os exemplos desta seção têm um, em `src/Web/Estrutura/Shared/Layout/layout.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var App\Shared\ApplicationParams $applicationParams
 * @var string $content
 * @var string $secao
 * @var array<string, string> $menu
 */

$this->beginPage();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="<?= Html::encode($applicationParams->charset) ?>">
    <title><?= Html::encode($this->getTitle()) ?> | <?= Html::encode($secao) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<header>
    <strong><?= Html::encode($secao) ?></strong>
    <nav>
        <?php foreach ($menu as $rotulo => $url): ?>
            <a href="<?= Html::encodeAttribute($url) ?>"><?= Html::encode($rotulo) ?></a>
        <?php endforeach ?>
    </nav>
</header>

<main>
    <?= $content ?>
</main>

<?php if ($this->hasBlock('rodape')): ?>
    <footer><?= $this->getBlock('rodape') ?></footer>
<?php endif ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
```

Os métodos `beginPage()`, `head()`, `beginBody()`, `endBody()` e `endPage()` marcam os pontos onde a `WebView` insere o que foi registrado: meta tags, CSS e JS. Sem eles, nada registrado aparece na página.

As variáveis `$secao` e `$menu` não vêm da action. Elas vêm de uma injeção, vista mais abaixo.

A action escolhe o layout com `withLayout()`. Em `src/Web/Estrutura/Views/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Views;

use App\Web\Estrutura\Shared\Layout\SecaoInjection;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    private WebViewRenderer $viewRenderer;

    public function __construct(WebViewRenderer $viewRenderer)
    {
        $this->viewRenderer = $viewRenderer
            ->withLayout('@src/Web/Estrutura/Shared/Layout/layout.php')
            ->withAddedInjections(SecaoInjection::class);
    }

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'capitulos' => [
                ['titulo' => 'Visão geral', 'palavras' => 900],
                ['titulo' => 'Views', 'palavras' => 1400],
                ['titulo' => 'Widgets', 'palavras' => 1100],
            ],
        ]);
    }
}
```

Os métodos `with*()` devolvem um renderer novo, sem alterar o original. O `WebViewRenderer` que o container entrega para as outras actions continua com o layout padrão. O caminho do layout aceita aliases, como `@src`.

A view, em `src/Web/Estrutura/Views/template.php`:

```php
<?php

declare(strict_types=1);

/**
 * @var Yiisoft\View\WebView $this
 * @var array<array{titulo: string, palavras: int}> $capitulos
 */

$this->setTitle('Capítulos');
$this->setBlock('rodape', 'Página gerada com um layout próprio da seção.');
?>

<h1>Capítulos</h1>

<ul>
    <?php foreach ($capitulos as $capitulo): ?>
        <?= $this->render('./_capitulo', ['capitulo' => $capitulo]) ?>
    <?php endforeach ?>
</ul>
```

E a view parcial, em `src/Web/Estrutura/Views/_capitulo.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array{titulo: string, palavras: int} $capitulo
 */
?>
<li><?= Html::encode($capitulo['titulo']) ?> (<?= $capitulo['palavras'] ?> palavras)</li>
```

Três recursos da `WebView` aparecem aqui:

- `setTitle()` define o título que o layout lê com `getTitle()`.
- `setBlock()` guarda um trecho de HTML com um nome. O layout pergunta com `hasBlock()` e imprime com `getBlock()`. Isso funciona porque a view é renderizada antes do layout. Diferente do Yii2, não existe `beginBlock()`/`endBlock()`: o conteúdo é passado como string.
- `$this->render('./_capitulo', ...)` renderiza outra view, relativa à atual. O resultado é uma string, sem layout. A view parcial só enxerga as variáveis que você passa e os parâmetros comuns.

A rota:

```php
Route::get('/estrutura/views')
    ->action(Estrutura\Views\Action::class)
    ->name('estrutura/views'),
```

O HTML de `/estrutura/views`:

```plaintext
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Capítulos | Estrutura da aplicação</title>
    <meta name="csrf" content="...">
<meta name="description" content="Exemplos da seção Estrutura da aplicação"></head>
<body>
<header>
    <strong>Estrutura da aplicação</strong>
    <nav>
                    <a href="/estrutura/views">Views</a>
                    <a href="/estrutura/artigos">Artigos</a>
                    <a href="/estrutura/contato">Contato</a>
            </nav>
</header>
<main>
<h1>Capítulos</h1>
<ul>
            <li>Visão geral (900 palavras)</li>
            <li>Views (1400 palavras)</li>
            <li>Widgets (1100 palavras)</li>
    </ul>
</main>
    <footer>Página gerada com um layout próprio da seção.</footer>
</body>
</html>
```

As linhas em branco foram removidas e o token CSRF foi abreviado.

Para renderizar sem layout nenhum, use `renderPartial()` ou `withLayout(null)`. É útil para fragmentos de HTML pedidos por JavaScript.

## Injeções de view

Uma injeção é uma classe que fornece variáveis ou tags para todas as views renderizadas por um `WebViewRenderer`. Ela implementa uma ou mais destas interfaces, do namespace `Yiisoft\Yii\View\Renderer`:

| Interface | Método | O que fornece |
|---|---|---|
| `CommonParametersInjectionInterface` | `getCommonParameters()` | variáveis para a view e o layout |
| `LayoutParametersInjectionInterface` | `getLayoutParameters()` | variáveis só para o layout |
| `MetaTagsInjectionInterface` | `getMetaTags()` | tags `<meta>` |
| `LinkTagsInjectionInterface` | `getLinkTags()` | tags `<link>` |

O template já usa uma: a `CsrfViewInjection`, registrada nos parâmetros acima. Ela implementa a primeira e a terceira interface. É ela que cria a variável `$csrf` dos formulários e a meta tag `csrf` que aparece no HTML acima.

A injeção do layout desta seção, em `src/Web/Estrutura/Shared/Layout/SecaoInjection.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Shared\Layout;

use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\View\Renderer\LayoutParametersInjectionInterface;
use Yiisoft\Yii\View\Renderer\MetaTagsInjectionInterface;

final readonly class SecaoInjection implements LayoutParametersInjectionInterface, MetaTagsInjectionInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getLayoutParameters(): array
    {
        return [
            'secao' => 'Estrutura da aplicação',
            'menu' => [
                'Views' => $this->urlGenerator->generate('estrutura/views'),
                'Artigos' => $this->urlGenerator->generate('estrutura/artigos'),
                'Contato' => $this->urlGenerator->generate('estrutura/contato'),
            ],
        ];
    }

    public function getMetaTags(): array
    {
        return [
            'description' => [
                'name' => 'description',
                'content' => 'Exemplos da seção Estrutura da aplicação',
            ],
        ];
    }
}
```

A injeção é criada pelo container, então pode receber serviços no construtor. Cada meta tag é um array de atributos; a chave (`description`) identifica a tag, e uma injeção posterior com a mesma chave substitui a anterior.

Há duas formas de registrar uma injeção:

- Para todas as páginas: na lista `injections` dos parâmetros de `yiisoft/yii-view-renderer`, como a `CsrfViewInjection`.
- Só para algumas: com `withAddedInjections()` no renderer, como a action acima faz. O `withInjections()` substitui a lista inteira, inclusive a do CSRF, então prefira o `withAddedInjections()`.

Os parâmetros de layout não sobrescrevem variáveis que já existem na `WebView`. Se a view chamar `$this->setParameter('secao', 'Outra')`, o layout mostra "Outra".

O pacote também tem a classe `LayoutSpecificInjections`, que agrupa injeções que só valem quando um determinado layout está em uso. Ela vai na mesma lista `injections`: `new LayoutSpecificInjections('@src/caminho/layout.php', new MinhaInjecao())`.

## Comparando com o Yii2

- No Yii2, o controller renderizava com `$this->render()`, e a view acessava o controller por `$this->context`. No Yii3, a action recebe o `WebViewRenderer` e a view não sabe quem a chamou.
- O `$this->params` do Yii2, usado para passar dados da view para o layout, deu lugar a `setParameter()`, `setBlock()` e às injeções.
- `beginBlock()`/`endBlock()` foram substituídos por `setBlock()` com uma string.
- `Yii::$app->view->title` virou `$this->setTitle()` e `$this->getTitle()`.
- As variáveis disponíveis em todas as views vêm da configuração (`yiisoft/view` → `parameters`) e das injeções, e não de componentes globais.
