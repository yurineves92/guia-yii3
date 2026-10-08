# Ordenação

Deixar o usuário ordenar uma lista clicando no cabeçalho da coluna parece simples, mas tem dois cuidados: o valor vem da URL, então não pode virar SQL sem controle, e às vezes uma "coluna" da tela corresponde a mais de um campo no banco. A classe `Sort`, do `yiisoft/data`, cuida dos dois. Este capítulo mostra como configurá-la, como ler a ordem da URL e como montar os links.

## A classe Sort

Um `Sort` guarda duas coisas:

- A configuração: quais campos podem ser ordenados e, para cada um, o que "crescente" e "decrescente" significam.
- A ordem atual: os campos que o usuário escolheu, com a direção.

A partir delas, o método `getCriteria()` produz a lista final de campos e direções que vai para o data reader.

Existem duas formas de criar:

- `Sort::only([...])` aceita só os campos da configuração. Um campo desconhecido vindo do usuário é ignorado.
- `Sort::any([...])` aceita qualquer campo. Os que não estão na configuração passam como vieram.

Para ordenação que vem da URL, use sempre `only()`. Com `any()`, o texto do usuário chega ao `ORDER BY`. Um nome simples, como `senha`, vira `ORDER BY "senha"`, entre aspas. Mas o query builder trata qualquer coisa com parênteses como expressão e não põe aspas: com `Sort::any()`, o valor `(SELECT 1)` vira `ORDER BY (SELECT 1)` e é executado. Isso é uma porta aberta para SQL injection.

## Configurando os campos

A configuração é um array. Um campo simples é só o nome. Um campo com regras próprias é uma chave com as opções `asc`, `desc` e `default`:

```php
$sort = Sort::only([
    'titulo',
    'autor' => [
        'asc' => ['autor' => SORT_ASC, 'ano' => SORT_ASC],
        'desc' => ['autor' => SORT_DESC, 'ano' => SORT_ASC],
    ],
    'ano',
    'preco' => ['default' => 'desc'],
]);
```

- `titulo` e `ano` ordenam por eles mesmos, em qualquer direção.
- `autor` é um campo lógico: ordenar por autor ordena pelo nome e, dentro do mesmo autor, pelo ano, sempre do mais antigo para o mais novo. As chaves `asc` e `desc` dizem quais campos reais usar em cada direção.
- `preco` tem a direção padrão invertida: o primeiro clique mostra os mais caros. Sem `default`, a direção padrão é `asc`.

Os nomes dentro de `asc` e `desc` são os campos que o data reader conhece. Com o `QueryDataReader`, são as colunas da consulta.

## Lendo a ordem da URL

A convenção do `yiisoft/data` é uma string com os campos separados por vírgula, e um `-` na frente para decrescente: `preco`, `-preco`, `-autor,titulo`. O método `withOrderString()` interpreta essa string, e `getOrderAsString()` faz o caminho inverso. Para montar a ordem a partir de um array, use `withOrder(['preco' => 'desc'])`.

Veja o que `getCriteria()` produz com a configuração acima (`SORT_ASC` é `4` e `SORT_DESC` é `3`):

```plaintext
''               {"titulo":4,"autor":4,"ano":4,"preco":3}
'-preco'         {"preco":3,"titulo":4,"autor":4,"ano":4}
'autor'          {"autor":4,"ano":4,"titulo":4,"preco":3}
'-autor,titulo'  {"autor":3,"ano":4,"titulo":4,"preco":3}
'senha'          {"titulo":4,"autor":4,"ano":4,"preco":3}
```

Dois comportamentos aparecem aqui:

- Depois dos campos escolhidos, o `Sort` acrescenta os outros campos da configuração, cada um na sua direção padrão. Eles funcionam como critério de desempate e garantem uma ordem estável. Sem nada na URL, vale só essa ordem padrão, que começa pelo título. Se você não quer esse acréscimo, chame `withoutDefaultSorting()`: aí `'-preco'` produz só `{"preco":3}`.
- `senha` não está na configuração e foi descartado.

## A página

Crie `src/Web/Dados/Ordenacao/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Dados\Ordenacao;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Db\QueryDataReader;
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

    public function __invoke(#[Query('ordem')] string $ordem = ''): ResponseInterface
    {
        $query = $this->db
            ->select(['titulo' => 'l.titulo', 'autor' => 'a.nome', 'ano' => 'l.ano', 'preco' => 'l.preco'])
            ->from(['l' => 'livro'])
            ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id');

        $sort = Sort::only([
            'titulo',
            'autor' => [
                'asc' => ['autor' => SORT_ASC, 'ano' => SORT_ASC],
                'desc' => ['autor' => SORT_DESC, 'ano' => SORT_ASC],
            ],
            'ano',
            'preco' => ['default' => 'desc'],
        ])->withOrderString($ordem);

        $leitor = (new QueryDataReader($query))->withSort($sort);

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'sort' => $sort,
            'livros' => $leitor->read(),
        ]);
    }
}
```

A consulta não tem `orderBy()`. Quem ordena é o data reader: `withSort()` devolve um novo leitor, que transforma os critérios em `ORDER BY` na hora de ler. Se a consulta já tivesse um `orderBy()`, os critérios do `Sort` seriam acrescentados depois dele, e a escolha do usuário teria efeito só no desempate.

As colunas da consulta têm alias (`'autor' => 'a.nome'`), e o `Sort` usa esses aliases. Com `?ordem=-preco`, o SQL fica:

```plaintext
SELECT "l"."titulo" AS "titulo", "a"."nome" AS "autor", "l"."ano" AS "ano", "l"."preco" AS "preco"
FROM "livro" "l" INNER JOIN "autor" "a" ON a.id = l.autor_id ORDER BY "preco" DESC, "titulo", "autor", "ano"
```

Usar o alias funciona no `ORDER BY` em qualquer banco, mas no `WHERE` não: MySQL e PostgreSQL recusam (o SQLite aceita, como extensão própria). Quando o mesmo leitor também for filtrado, mapeie os nomes para as colunas reais com o `fieldMapper`, mostrado no capítulo [Data readers](/dados/data-readers).

## Links no cabeçalho

Cada cabeçalho é um link que ordena pela coluna. Se a lista já está ordenada por ela, o link inverte a direção; se não está, usa a direção padrão do campo. A view, em `src/Web/Dados/Ordenacao/template.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Reader\Sort $sort
 * @var iterable<array> $livros
 */

$this->setTitle('Livros ordenados');

$cabecalho = static function (string $campo, string $rotulo) use ($sort, $urlGenerator): string {
    $atual = $sort->getOrder()[$campo] ?? null;
    $proxima = match ($atual) {
        'asc' => 'desc',
        'desc' => 'asc',
        null => $sort->getDefaultOrder()[$campo],
    };
    $url = $urlGenerator->generate('dados/ordenacao', queryParameters: [
        'ordem' => ($proxima === 'desc' ? '-' : '') . $campo,
    ]);

    return Html::a($rotulo . ($atual === null ? '' : " ($atual)"), $url)->render();
};
?>

<h1>Livros ordenados</h1>

<table>
    <tr>
        <th><?= $cabecalho('titulo', 'Título') ?></th>
        <th><?= $cabecalho('autor', 'Autor') ?></th>
        <th><?= $cabecalho('ano', 'Ano') ?></th>
        <th><?= $cabecalho('preco', 'Preço') ?></th>
    </tr>
    <?php foreach ($livros as $livro): ?>
        <tr>
            <td><?= Html::encode($livro['titulo']) ?></td>
            <td><?= Html::encode($livro['autor']) ?></td>
            <td><?= Html::encode($livro['ano']) ?></td>
            <td><?= Html::encode($livro['preco']) ?></td>
        </tr>
    <?php endforeach ?>
</table>
```

`getOrder()` devolve só a ordem escolhida pelo usuário, como `['preco' => 'desc']`, sem os campos de desempate. `getDefaultOrder()` devolve a direção padrão de cada campo configurado.

A rota:

```php
Route::get('/dados/ordenacao')
    ->action(Web\Dados\Ordenacao\Action::class)
    ->name('dados/ordenacao'),
```

Testando:

- `/dados/ordenacao` lista por título, de "A Hora da Estrela" a "Vidas Secas". O link do cabeçalho "Preço" aponta para `?ordem=-preco`, por causa do `default`.
- `/dados/ordenacao?ordem=-preco` começa por "Dona Flor e Seus Dois Maridos" (74.9) e mostra o cabeçalho "Preço (desc)", que agora aponta para `?ordem=preco`.
- `/dados/ordenacao?ordem=autor` agrupa por autor, começando por Clarice Lispector, com os livros de cada um em ordem de ano.
- `/dados/ordenacao?ordem=senha` ignora o campo e mostra a ordem padrão.

## Ordenação e paginação juntas

O paginador também aceita um `Sort`: `$paginador->withSort($sort)` repassa para o data reader. Ao montar os links de página, inclua o parâmetro `ordem` atual (`$sort->getOrderAsString()`), senão a ordenação se perde ao trocar de página. E ao trocar a ordenação, volte para a página 1: a página 3 de uma ordem não tem relação com a página 3 de outra.

O `GridView`, do capítulo [Widgets de dados](/dados/widgets-de-dados), faz tudo isso sozinho: gera os links de cabeçalho, mantém a ordem na paginação e lê o parâmetro `sort` da URL.

## Comparando com o Yii2

- O `yii\data\Sort` do Yii2 tinha a mesma ideia de atributos com `asc`, `desc` e `default`, e também usava `-` para decrescente na URL. A diferença é que ele lia a requisição sozinho; o `Sort` do Yii3 recebe a string, e quem lê a URL é a action.
- O `Sort` do Yii2 gerava os links (`$sort->link('nome')`). No Yii3, a view monta os links, ou o `GridView` faz isso.
