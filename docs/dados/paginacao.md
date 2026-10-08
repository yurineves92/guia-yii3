# Paginação

Uma listagem com milhares de linhas precisa ser mostrada em partes. O pacote `yiisoft/data` traz dois paginadores: o `OffsetPaginator`, que numera as páginas, e o `KeysetPaginator`, que avança a partir do último item visto. Este capítulo mostra os dois e como montar os links entre as páginas.

O capítulo [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados) já usou o `OffsetPaginator` de forma simples. Aqui ele aparece com mais detalhes.

## Paginadores e data readers

Um paginador não lê dados sozinho. Ele embrulha um data reader, que é quem sabe buscar os itens: o `QueryDataReader` do `yiisoft/data-db` para consultas ao banco, ou o `IterableDataReader` para arrays. O paginador só diz ao data reader quantos itens pular e quantos ler. O capítulo [Data readers](/dados/data-readers) mostra os leitores em detalhe.

Os dois paginadores implementam `PaginatorInterface` e trabalham com tokens: um `PageToken` carrega o valor que identifica a página (o número dela, no offset, ou o último valor visto, no keyset) e se ele aponta para a frente (`PageToken::next()`) ou para trás (`PageToken::previous()`).

## OffsetPaginator

O `OffsetPaginator` traduz o número da página em `LIMIT` e `OFFSET`. Crie `src/Web/Dados/Paginacao/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Dados\Paginacao;

use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\InvalidPageException;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Paginator\PageToken;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
        private NotFoundHandler $notFoundHandler,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[Query('pagina')] string $pagina = '1'): ResponseInterface
    {
        $query = $this->db
            ->select(['titulo', 'ano'])
            ->from('livro')
            ->orderBy(['titulo' => SORT_ASC]);

        $paginador = (new OffsetPaginator(new QueryDataReader($query)))->withPageSize(5);

        try {
            $paginador = $paginador->withToken(PageToken::next($pagina));
            $livros = iterator_to_array($paginador->read());
        } catch (InvalidPageException) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'paginador' => $paginador,
            'livros' => $livros,
        ]);
    }
}
```

O que acontece aqui:

- `withPageSize(5)` define 5 itens por página. O padrão é 10.
- `withToken(PageToken::next($pagina))` escolhe a página a partir do texto da URL. Se o valor não for um número maior que zero (`?pagina=0`, `?pagina=abc`), ele lança `InvalidPageException`. Quando você já tem um inteiro, `withCurrentPage(3)` faz o mesmo.
- `read()` executa a consulta e devolve só os itens da página. Pedir uma página além da última (`?pagina=4`, com 12 livros) faz o `read()` lançar `PageNotFoundException`, que é uma `InvalidPageException`. Por isso um único `catch` cobre os dois casos e devolve 404.
- O `read()` devolve um gerador, e a exceção só aparece quando alguém percorre os itens. O `iterator_to_array()` força a leitura ainda dentro do `try`, em vez de deixar o erro estourar no meio da view.

Como os paginadores são imutáveis, `withPageSize()` e `withToken()` devolvem um objeto novo, e por isso o resultado é atribuído de volta a `$paginador`.

Além de `read()`, o `OffsetPaginator` informa:

- `getTotalItems()` e `getTotalPages()`: total de itens e de páginas. Fazem um `COUNT(*)` no banco.
- `getCurrentPage()`, `getPageSize()` e `getCurrentPageSize()` (quantos itens a página atual tem, menos que o tamanho na última).
- `getOffset()`: quantos itens foram pulados.
- `isOnFirstPage()`, `isOnLastPage()` e `isPaginationRequired()` (se há mais de uma página).
- `nextPage()` e `previousPage()`: o paginador da página seguinte ou anterior, ou `null`.

## Links de página

Com essas informações, a view monta a navegação. Em `src/Web/Dados/Paginacao/template.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Paginator\OffsetPaginator $paginador
 * @var array[] $livros
 */

$this->setTitle('Livros por título');

$url = static fn (int $pagina): string => $urlGenerator->generate(
    'dados/paginacao',
    queryParameters: $pagina === 1 ? [] : ['pagina' => $pagina],
);
$inicio = $paginador->getOffset() + 1;
$fim = $paginador->getOffset() + $paginador->getCurrentPageSize();
?>

<h1>Livros por título</h1>

<p>Livros <?= $inicio ?> a <?= $fim ?> de <?= $paginador->getTotalItems() ?></p>

<ul>
    <?php foreach ($livros as $livro): ?>
        <li><?= Html::encode($livro['titulo']) ?> (<?= Html::encode($livro['ano']) ?>)</li>
    <?php endforeach ?>
</ul>

<nav>
    <?php if (!$paginador->isOnFirstPage()): ?>
        <a href="<?= $url($paginador->getCurrentPage() - 1) ?>">Anterior</a>
    <?php endif ?>

    <?php for ($pagina = 1; $pagina <= $paginador->getTotalPages(); $pagina++): ?>
        <?php if ($pagina === $paginador->getCurrentPage()): ?>
            <strong><?= $pagina ?></strong>
        <?php else: ?>
            <a href="<?= $url($pagina) ?>"><?= $pagina ?></a>
        <?php endif ?>
    <?php endfor ?>

    <?php if (!$paginador->isOnLastPage()): ?>
        <a href="<?= $url($paginador->getCurrentPage() + 1) ?>">Próxima</a>
    <?php endif ?>
</nav>
```

A closure `$url` gera o endereço de cada página pelo nome da rota, e deixa a página 1 sem parâmetro, para que `/dados/paginacao` e `/dados/paginacao?pagina=1` não virem duas URLs diferentes para o mesmo conteúdo.

A rota:

```php
Route::get('/dados/paginacao')
    ->action(Web\Dados\Paginacao\Action::class)
    ->name('dados/paginacao'),
```

Em `/dados/paginacao` aparecem "Livros 1 a 5 de 12", de "A Hora da Estrela" a "Dom Casmurro", e a navegação `1 2 3 Próxima`. Em `?pagina=3`, "Livros 11 a 12 de 12" com "São Bernardo" e "Vidas Secas", e a navegação `Anterior 1 2 3`. Já `?pagina=4`, `?pagina=0` e `?pagina=abc` respondem 404.

Com muitas páginas, uma lista de 1 até o total fica longa. Nesse caso, mostre só uma janela em volta da página atual, ou use o widget `OffsetPagination` do `yiisoft/yii-dataview`, que já faz isso (o método `maxNavLinkCount()` controla o tamanho da janela). O `GridView` e o `ListView`, do capítulo [Widgets de dados](/dados/widgets-de-dados), montam a paginação sozinhos.

## KeysetPaginator

O offset tem dois problemas em tabelas grandes ou que mudam muito:

- Para chegar à página 1000, o banco precisa ler e descartar todas as linhas anteriores. Quanto mais longe, mais lento.
- Se um registro é inserido ou apagado enquanto o usuário navega, os itens escorregam de página: um aparece duas vezes, outro some.

O `KeysetPaginator` (também chamado de paginação por cursor) resolve os dois. Em vez de "pule 20", ele pede "os próximos depois deste valor": `WHERE slug > 'dom-casmurro' ORDER BY slug LIMIT 6`. Com um índice na coluna, o banco vai direto ao ponto, em qualquer página.

Em troca:

- Não existe total de páginas nem salto para a página 7. Só "anterior" e "próxima".
- Os dados precisam estar ordenados, e por uma coluna de valores únicos. Com valores repetidos, itens com o mesmo valor na fronteira entre duas páginas podem ser pulados.

Crie `src/Web/Dados/Keyset/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Dados\Keyset;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\KeysetPaginator;
use Yiisoft\Data\Paginator\PageToken;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
    ) {}

    public function __invoke(
        #[Query('depois')] ?string $depois = null,
        #[Query('antes')] ?string $antes = null,
    ): ResponseInterface {
        $leitor = (new QueryDataReader($this->db->select(['slug', 'titulo', 'ano'])->from('livro')))
            ->withSort(Sort::only(['slug']));

        $paginador = (new KeysetPaginator($leitor))->withPageSize(5);

        if ($depois !== null) {
            $paginador = $paginador->withToken(PageToken::next($depois));
        } elseif ($antes !== null) {
            $paginador = $paginador->withToken(PageToken::previous($antes));
        }

        return $this->viewRenderer->render(__DIR__ . '/template', ['paginador' => $paginador]);
    }
}
```

A ordenação aqui não está na consulta, e sim no data reader, com `withSort(Sort::only(['slug']))`. O paginador precisa dela para saber qual coluna comparar, e lança `InvalidArgumentException` se o data reader não tiver ordenação. A coluna `slug` é única na tabela, o que atende à exigência acima. O objeto `Sort` é o assunto do capítulo [Ordenação](/dados/ordenacao).

Sem token, o paginador mostra a primeira página. Com `PageToken::next('dom-casmurro')`, mostra os itens depois desse slug; com `PageToken::previous('dona-flor')`, os itens antes dele.

Para saber se existe próxima página, o paginador pede um item a mais do que o tamanho da página (`LIMIT 6` para páginas de 5). Para saber se existe anterior, faz uma consulta extra com a comparação invertida.

### Links com o widget de paginação

Os tokens das páginas vizinhas estão em `getNextToken()` e `getPreviousToken()`, que devolvem `null` quando não há página naquela direção. Você pode montar os links com eles, como na seção anterior, ou usar o widget `KeysetPagination` do `yiisoft/yii-dataview`, que funciona também fora do `GridView`. Em `src/Web/Dados/Keyset/template.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Yii\DataView\Pagination\KeysetPagination;
use Yiisoft\Yii\DataView\Pagination\PaginationContext;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Paginator\KeysetPaginator $paginador
 */

$this->setTitle('Livros por slug');
?>

<h1>Livros por slug</h1>

<ul>
    <?php foreach ($paginador->read() as $livro): ?>
        <li><?= Html::encode($livro['titulo']) ?> (<?= Html::encode($livro['slug']) ?>)</li>
    <?php endforeach ?>
</ul>

<?= KeysetPagination::widget()
    ->paginator($paginador)
    ->context(new PaginationContext(
        nextUrlPattern: $urlGenerator->generate(
            'dados/keyset',
            queryParameters: ['depois' => PaginationContext::URL_PLACEHOLDER],
        ),
        previousUrlPattern: $urlGenerator->generate(
            'dados/keyset',
            queryParameters: ['antes' => PaginationContext::URL_PLACEHOLDER],
        ),
        firstPageUrl: $urlGenerator->generate('dados/keyset'),
    ))
    ->labelPrevious('Anterior')
    ->labelNext('Próxima') ?>
```

O widget precisa de um `PaginationContext` com três endereços: o modelo do link para a frente, o modelo do link para trás e o da primeira página. Nos modelos, a constante `URL_PLACEHOLDER` marca onde entra o valor do token. Os rótulos padrão são setas; `labelPrevious()` e `labelNext()` trocam por texto.

A rota:

```php
Route::get('/dados/keyset')
    ->action(Web\Dados\Keyset\Action::class)
    ->name('dados/keyset'),
```

Em `/dados/keyset`, a página mostra os cinco primeiros slugs, de `a-hora-da-estrela` a `dom-casmurro`, e o widget gera:

```plaintext
<nav>
<span>Anterior</span>
<a href="/dados/keyset?depois=dom-casmurro">Próxima</a>
</nav>
```

Seguindo o link, `?depois=dom-casmurro` traz de `dona-flor` a `quincas-borba`, com os links `?antes=dona-flor` e `?depois=quincas-borba`. A última página, `?depois=perto-do-coracao-selvagem`, tem três itens e o "Próxima" desativado.

## Qual usar

Use `OffsetPaginator` quando o usuário precisa ver o total ou pular para uma página específica, e a tabela tem um tamanho razoável: telas de administração, resultados de busca. Use `KeysetPaginator` para listas longas percorridas em sequência, como feeds, históricos e exportações, ou em APIs, onde o cliente só pede "a próxima".

## Comparando com o Yii2

- O `yii\data\Pagination` do Yii2 só calculava `offset` e `limit`, e o `ActiveDataProvider` juntava consulta, paginação e ordenação. No Yii3, o paginador embrulha um data reader e já lê os itens.
- Paginação por keyset não existia pronta no Yii2.
- O `LinkPager` do Yii2 corresponde aos widgets `OffsetPagination` e `KeysetPagination` do `yiisoft/yii-dataview`.
