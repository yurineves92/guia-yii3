# Widgets de dados

Nos capítulos anteriores, cada página montou à mão a tabela, os links de ordenação e a paginação. O pacote `yiisoft/yii-dataview` junta tudo isso em widgets: o `GridView` mostra os dados numa tabela, o `ListView` mostra cada item com uma view sua, e os dois cuidam de paginação, ordenação pelo cabeçalho e resumo. Este capítulo mostra os dois, as colunas e a coluna de ações.

```bash
composer require yiisoft/yii-dataview
```

Os exemplos usam o `Catalogo` do capítulo [Data readers](/dados/data-readers) e o `Formatador` do capítulo [Formatação](/dados/formatacao).

## GridView

A action só prepara o leitor. Crie `src/Web/Dados/Grade/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Dados\Grade;

use App\Dados\Catalogo;
use App\Dados\Formatador;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private Catalogo $catalogo,
        private Formatador $formatador,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $paginador = (new OffsetPaginator($this->catalogo->livros()))->withPageSize(5);

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'paginador' => $paginador,
            'formatador' => $this->formatador,
        ]);
    }
}
```

Repare no que a action não faz: ela não lê a página nem a ordenação da URL. Isso fica com o widget. O paginador aqui serve só para definir 5 itens por página; sem ele, o widget criaria um `OffsetPaginator` com 10.

O widget vai na view, `src/Web/Dados/Grade/template.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Yii\DataView\GridView\Column\ActionButton;
use Yiisoft\Yii\DataView\GridView\Column\ActionColumn;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;
use Yiisoft\Yii\DataView\GridView\Column\SerialColumn;
use Yiisoft\Yii\DataView\GridView\GridView;
use Yiisoft\Yii\DataView\YiiRouter\UrlCreator;
use Yiisoft\Yii\DataView\YiiRouter\UrlParameterProvider;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 * @var Yiisoft\Data\Paginator\OffsetPaginator $paginador
 * @var App\Dados\Formatador $formatador
 */

$this->setTitle('Catálogo');
?>

<h1>Catálogo</h1>

<?= GridView::widget()
    ->dataReader($paginador)
    ->urlCreator(new UrlCreator($urlGenerator))
    ->urlParameterProvider(new UrlParameterProvider($currentRoute))
    ->summaryTemplate('Livros {begin} a {end} de {totalCount}')
    ->noResultsText('Nenhum livro encontrado.')
    ->offsetPaginationConfig([
        'labelFirst()' => ['Primeira'],
        'labelPrevious()' => ['Anterior'],
        'labelNext()' => ['Próxima'],
        'labelLast()' => ['Última'],
    ])
    ->columns(
        new SerialColumn(header: '#'),
        new DataColumn('titulo', header: 'Título'),
        new DataColumn('autor', header: 'Autor'),
        new DataColumn('ano', header: 'Ano'),
        new DataColumn(
            'preco',
            header: 'Preço',
            content: static fn (array $livro): string => $formatador->moeda($livro['preco']),
        ),
        new ActionColumn(
            header: 'Ações',
            buttons: [
                'ver' => new ActionButton(
                    content: 'Ver',
                    url: static fn (array $livro): string => $urlGenerator->generate(
                        'banco-de-dados/livro',
                        ['id' => $livro['id']],
                    ),
                ),
            ],
        ),
    ) ?>
```

Por partes:

- `dataReader()` recebe o leitor ou o paginador.
- `urlCreator()` e `urlParameterProvider()` ligam o widget ao roteador. O `UrlParameterProvider` lê os parâmetros `page` e `sort` da URL atual, e o `UrlCreator` gera os links a partir da rota atual. Sem os dois, o widget mostra a primeira página e os links viram âncoras como `#page=2`. Os nomes dos parâmetros podem ser trocados com `pageParameterName()` e `sortParameterName()`.
- `summaryTemplate()` troca o resumo, que por padrão vem em inglês ("Page 1 of 3"). Os marcadores disponíveis são `{begin}`, `{end}`, `{count}`, `{totalCount}`, `{currentPage}` e `{totalPages}`.
- `noResultsText()` é o texto para quando não há itens.
- `offsetPaginationConfig()` configura o widget de paginação que o `GridView` cria. As chaves são nomes de método com `()` e os valores, a lista de argumentos. Os rótulos padrão são setas (`⟪ ⟨ ⟩ ⟫`).

O `$urlGenerator` e o `$currentRoute` vêm dos parâmetros comuns de view, que o template já configura para todas as views.

A rota:

```php
Route::get('/dados/grade')
    ->action(Web\Dados\Grade\Action::class)
    ->name('dados/grade'),
```

Em `/dados/grade`, a tabela mostra os 5 primeiros livros em ordem de título, de "A Hora da Estrela" a "Dom Casmurro", o resumo "Livros 1 a 5 de 12" e a navegação. Um trecho do HTML gerado:

```plaintext
<thead>
<tr>
<th>#</th>
<th><a href="/dados/grade?sort=titulo">Título</a></th>
<th><a href="/dados/grade?sort=autor">Autor</a></th>
<th><a href="/dados/grade?sort=ano">Ano</a></th>
<th><a href="/dados/grade?sort=preco">Preço</a></th>
<th>Ações</th>
</tr>
</thead>
<tbody>
<tr>
<td>1</td>
<td>A Hora da Estrela</td>
<td>Clarice Lispector</td>
<td>1977</td>
<td>R$ 44,90</td>
<td>
<a href="/banco-de-dados/livros/6">Ver</a>
</td>
</tr>
```

### Ordenação e paginação

Os cabeçalhos viraram links porque o leitor tem um `Sort` com esses campos (o `Sort::only()` do `Catalogo`). Uma `DataColumn` só é ordenável se o campo dela estiver na configuração do `Sort`; para desligar numa coluna específica, passe `withSorting: false`. Campos fora da configuração são ignorados mesmo que alguém escreva `?sort=senha` na URL.

Clicando em "Preço" duas vezes, a URL fica `?sort=-preco`, e o terceiro clique remove a ordenação. Na página 2 dessa ordem (`/dados/grade?sort=-preco&page=2`), a tabela mostra os itens 6 a 10, de "Angústia" (R$ 47,90) a "Vidas Secas" (R$ 39,90), e todos os links de página mantêm o `sort=-preco`.

Uma página que não existe, como `?page=9`, mostra a primeira página. Para tratar de outro jeito, use `ignoreMissingPage(false)`: aí o widget lança `PageNotFoundException`. Antes disso, ele chama a função registrada em `pageNotFoundExceptionCallback()`, se houver, que pode, por exemplo, lançar uma exceção própria da aplicação.

### Colunas

As colunas são objetos passados para `columns()`, na ordem em que aparecem:

- `DataColumn` mostra um campo do item. O primeiro argumento é o nome do campo; `header` é o título, que sem isso vira o nome do campo com a primeira letra maiúscula. Com `content`, você passa uma closure que recebe o item e devolve o que exibir, como o preço formatado acima. O conteúdo é escapado com `Html::encode()` por padrão; para HTML confiável, passe `encodeContent: false`.
- `SerialColumn` numera as linhas, continuando a contagem nas páginas seguintes.
- `CheckboxColumn` e `RadioColumn` põem um campo de seleção em cada linha, para ações em lote.
- `ActionColumn` mostra links de ação, como "ver", "editar" e "apagar".

`DataColumn` também aceita atributos HTML para o cabeçalho e as células (`headerAttributes`, `bodyAttributes`), classes CSS e `visible: false` para esconder a coluna.

### A coluna de ações

Sem configuração, a `ActionColumn` mostra três botões, `view`, `update` e `delete`, com ícones e com `#` como endereço. Na prática, você quase sempre define os seus. No exemplo, `buttons` tem um único botão `ver`, um `ActionButton` com o texto e uma closure que gera a URL a partir do item. O `ActionButton` também aceita `title`, `class` e `attributes`.

Outras opções da `ActionColumn`:

- `template` define a ordem dos botões, como `'{ver} {editar}'`. Sem ele, aparecem todos, na ordem do array.
- `visibleButtons` mostra ou esconde um botão por linha: `['apagar' => static fn (array $livro): bool => $livro['ano'] > 1950]`.
- `urlCreator` gera as URLs de todos os botões de uma vez. O pacote traz o `ActionColumnUrlCreator`, que monta o nome da rota a partir da rota atual e do nome do botão (por exemplo, `dados/grade/ver`) e passa a chave primária como parâmetro.
- Um botão também pode ser uma closure que recebe a URL e o contexto e devolve o HTML pronto.

Um botão de apagar deve levar a um formulário com POST e confirmação, não a um link GET. Links são seguidos por buscadores e por pré-carregamento de navegadores.

## ListView

O `ListView` serve para dados que não cabem bem numa tabela: cartões, listas de resultados, comentários. Cada item é renderizado por uma view sua. A action, em `src/Web/Dados/Lista/Action.php`, passa o leitor sem paginador:

```php
<?php

declare(strict_types=1);

namespace App\Web\Dados\Lista;

use App\Dados\Catalogo;
use App\Dados\Formatador;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private Catalogo $catalogo,
        private Formatador $formatador,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'livros' => $this->catalogo->livros(),
            'formatador' => $this->formatador,
        ]);
    }
}
```

A view da página, `src/Web/Dados/Lista/template.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Yii\DataView\ListView\ListView;
use Yiisoft\Yii\DataView\YiiRouter\UrlCreator;
use Yiisoft\Yii\DataView\YiiRouter\UrlParameterProvider;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 * @var Yiisoft\Data\Db\QueryDataReader $livros
 * @var App\Dados\Formatador $formatador
 */

$this->setTitle('Vitrine');
?>

<h1>Vitrine</h1>

<?= ListView::widget()
    ->dataReader($livros)
    ->urlCreator(new UrlCreator($urlGenerator))
    ->urlParameterProvider(new UrlParameterProvider($currentRoute))
    ->itemView(__DIR__ . '/_livro.php')
    ->itemViewParameters(['formatador' => $formatador])
    ->listAttributes(['class' => 'vitrine'])
    ->summaryTemplate('Página {currentPage} de {totalPages}')
    ->offsetPaginationConfig([
        'labelFirst()' => [null],
        'labelLast()' => [null],
        'labelPrevious()' => ['Anterior'],
        'labelNext()' => ['Próxima'],
    ]) ?>
```

E a view de cada item, `src/Web/Dados/Lista/_livro.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array $data
 * @var Yiisoft\Yii\DataView\ListView\ListItemContext $context
 * @var App\Dados\Formatador $formatador
 */
?>
<strong><?= Html::encode($data['titulo']) ?></strong>
<?= Html::encode($data['autor']) ?>, <?= $data['ano'] ?>.
<?= $formatador->moeda($data['preco']) ?>
```

- `itemView()` recebe o caminho do arquivo. A view do item recebe o item em `$data`, um `ListItemContext` em `$context` (com `key` e `index`) e o que você passar em `itemViewParameters()`. As variáveis comuns de view, como `$urlGenerator`, não chegam aqui: passe por `itemViewParameters()` o que precisar.
- Em vez de um arquivo, `itemView()` aceita uma closure `fn (array $livro, ListItemContext $context): string`, prática para itens de uma linha.
- Por padrão, a lista é um `<ul>` e cada item um `<li>`. `listTag()`, `itemTag()` e os métodos de atributos mudam isso.
- Passar `null` como rótulo esconde os links "primeira" e "última".

A rota:

```php
Route::get('/dados/lista')
    ->action(Web\Dados\Lista\Action::class)
    ->name('dados/lista'),
```

Como a action entregou o `QueryDataReader` direto, o widget criou um `OffsetPaginator` com o tamanho padrão de 10. Em `/dados/lista` aparecem 10 livros, "Página 1 de 2" e a navegação; `?page=2` mostra "São Bernardo" e "Vidas Secas". Cada item sai assim:

```plaintext
<li><strong>A Hora da Estrela</strong>
Clarice Lispector, 1977.
R$ 44,90</li>
```

O `ListView` não tem cabeçalho de coluna: ele não gera links de ordenação e ignora o parâmetro `sort` da URL. Se a lista precisa ser ordenável, leia a ordem na action e aplique no leitor com `withSort()`, montando os links como no capítulo [Ordenação](/dados/ordenacao).

## Aparência

Os widgets geram HTML sem classes de nenhum framework CSS. Para o Bootstrap 5, o pacote traz um tema pronto, com classes para a tabela, a paginação e os filtros. Os temas são aplicados pela configuração do `yiisoft/widget`; o capítulo [Widgets](/estrutura/widgets) explica como.

Para ajustes pontuais, os próprios widgets têm métodos como `tableClass()`, `tableAttributes()`, `containerAttributes()` e `layout()`, que define a ordem das partes (`{header}`, `{toolbar}`, `{items}`, `{summary}`, `{pager}` e `{pageSize}`).

O pacote também tem o `DetailView`, que mostra os campos de um único registro em forma de lista de rótulo e valor, útil para páginas de detalhe.

## Comparando com o Yii2

- O `GridView`, o `ListView` e o `DetailView` existem no Yii3 com os mesmos papéis. Em vez de `dataProvider`, recebem um data reader ou paginador.
- As colunas são objetos com argumentos nomeados (`new DataColumn('titulo', header: 'Título')`), não arrays de configuração (`['attribute' => 'titulo', 'label' => 'Título']`).
- O widget não lê a requisição sozinho: você entrega a ele um `UrlParameterProvider` e um `UrlCreator`.
- O `ActionColumn` do Yii2 gerava URLs como `controller/view?id=1` automaticamente. No Yii3, você define os botões e as URLs, ou usa o `ActionColumnUrlCreator`.
