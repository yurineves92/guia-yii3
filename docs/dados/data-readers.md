# Data readers

Paginação, ordenação e filtros são os mesmos problemas, venham os dados do banco, de um array ou de uma API. O pacote `yiisoft/data` define uma interface comum para "algo de onde se leem dados", e os paginadores, o `Sort` e os widgets de dados trabalham em cima dela, sem saber de onde os itens vêm. Este capítulo mostra essas interfaces, os filtros e os dois leitores que você vai usar no dia a dia.

## As interfaces

Um data reader é um objeto imutável que sabe ler itens. O que ele sabe fazer além disso é dito pelas interfaces que implementa, todas em `Yiisoft\Data\Reader`:

- `ReadableDataInterface`: `read()` devolve os itens e `readOne()` devolve o primeiro.
- `CountableDataInterface`: `count()` devolve o total.
- `LimitableDataInterface` e `OffsetableDataInterface`: `withLimit()` e `withOffset()` cortam o resultado. É o que o `OffsetPaginator` usa.
- `SortableDataInterface`: `withSort()` recebe um `Sort`.
- `FilterableDataInterface`: `withFilter()` recebe um filtro.

A `DataReaderInterface` junta todas elas. Cada método `with*()` devolve um leitor novo, então você pode partir de uma base e derivar variações sem uma afetar a outra.

Dois leitores vêm prontos:

- `IterableDataReader`, do `yiisoft/data`, lê de um array ou de qualquer `iterable`. Filtra, ordena e corta em PHP.
- `QueryDataReader`, do `yiisoft/data-db`, lê de uma consulta do query builder. Transforma filtro, ordenação e limites em SQL.

Se os dados vêm de outro lugar (uma API externa, um índice de busca), você pode escrever o seu leitor implementando as interfaces que fizerem sentido.

## Filtros

Filtros são objetos do namespace `Yiisoft\Data\Reader\Filter`, e o mesmo filtro funciona em qualquer leitor que implemente `FilterableDataInterface`:

- Comparação: `Equals`, `GreaterThan`, `GreaterThanOrEqual`, `LessThan`, `LessThanOrEqual`, `Between`, `In`, `EqualsNull`.
- Texto: `Like`, com um `LikeMode` (`Contains`, o padrão, `StartsWith` ou `EndsWith`) e um parâmetro para diferenciar maiúsculas.
- Combinação: `AndX`, `OrX` e `Not`.
- Especiais: `All`, que não filtra nada, e `None`, que não deixa passar nada.

```php
use Yiisoft\Data\Reader\Filter\AndX;
use Yiisoft\Data\Reader\Filter\Between;
use Yiisoft\Data\Reader\Filter\Equals;
use Yiisoft\Data\Reader\Filter\OrX;

$filtro = new OrX(
    new Equals('autorId', 1),
    new Between('ano', 1930, 1940),
);
```

Os nomes dos campos (`autorId`, `ano`) são os do leitor, e não necessariamente os do banco. A seguir você vai ver como o `QueryDataReader` liga um ao outro.

## Lendo de uma consulta

O `Catalogo`, em `src/Dados/Catalogo.php`, monta o leitor de livros que este capítulo e o próximo usam:

```php
<?php

declare(strict_types=1);

namespace App\Dados;

use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class Catalogo
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function livros(): QueryDataReader
    {
        $query = $this->db
            ->select([
                'id' => 'l.id',
                'titulo' => 'l.titulo',
                'autor' => 'a.nome',
                'ano' => 'l.ano',
                'preco' => 'l.preco',
            ])
            ->from(['l' => 'livro'])
            ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id');

        return new QueryDataReader(
            query: $query,
            sort: Sort::only(['titulo', 'autor', 'ano', 'preco']),
            fieldMapper: [
                'id' => 'l.id',
                'titulo' => 'l.titulo',
                'autor' => 'a.nome',
                'autorId' => 'l.autor_id',
                'ano' => 'l.ano',
                'preco' => 'l.preco',
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function autores(): array
    {
        return $this->db
            ->select(['nome', 'id'])
            ->from('autor')
            ->orderBy(['nome' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }
}
```

O construtor do `QueryDataReader` recebe a consulta e, opcionalmente, o `Sort` inicial, os limites, os filtros e o `fieldMapper`.

O `fieldMapper` traduz os nomes de campo do leitor para colunas da consulta, tanto nos filtros quanto na ordenação. Ele resolve três problemas:

- Com o `JOIN`, `id` sozinho é ambíguo (`livro.id` ou `autor.id`?). O mapa diz que é `l.id`.
- `autorId` não está entre as colunas selecionadas, mas pode ser filtrado: vira `l.autor_id`.
- A tela usa nomes que não precisam ser os do banco. Se a coluna mudar de nome, só o mapa muda.

Com o filtro de antes, mais `withLimit(3)` e `withOffset(1)`, o SQL gerado pelo leitor foi:

```plaintext
SELECT "l"."id" AS "id", "l"."titulo" AS "titulo", "a"."nome" AS "autor", "l"."ano" AS "ano", "l"."preco" AS "preco"
FROM "livro" "l" INNER JOIN "autor" "a" ON a.id = l.autor_id
WHERE ("l"."autor_id" = 1) OR ("l"."ano" BETWEEN 1930 AND 1940)
ORDER BY "l"."titulo", "a"."nome", "l"."ano", "l"."preco" LIMIT 3 OFFSET 1
```

O `ORDER BY` veio do `Sort` configurado no construtor, com a ordem padrão de todos os campos. O método `getPreparedQuery()` devolve essa consulta final, útil para conferir o SQL ou para reaproveitar a consulta em outro lugar.

Algumas observações sobre o `QueryDataReader`:

- `read()` executa a consulta uma vez e guarda o resultado no objeto. Ler de novo o mesmo leitor não vai ao banco.
- `count()` faz um `SELECT COUNT(*)` com os mesmos filtros, sem limite nem ordenação.
- Para tabelas grandes, `withBatchSize(100)` faz o `read()` buscar os itens em lotes de 100, em vez de carregar tudo na memória.

## Filtros vindos da URL

A página `/dados/filtros` monta os filtros a partir de um formulário de busca. Crie `src/Web/Dados/Filtros/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Dados\Filtros;

use App\Dados\Catalogo;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Reader\Filter\AndX;
use Yiisoft\Data\Reader\Filter\Equals;
use Yiisoft\Data\Reader\Filter\LessThanOrEqual;
use Yiisoft\Data\Reader\Filter\Like;
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    private const array NASCIMENTOS = [
        ['nome' => 'Machado de Assis', 'cidade' => 'Rio de Janeiro', 'nascimento' => 1839],
        ['nome' => 'Graciliano Ramos', 'cidade' => 'Quebrangulo', 'nascimento' => 1892],
        ['nome' => 'Cecília Meireles', 'cidade' => 'Rio de Janeiro', 'nascimento' => 1901],
        ['nome' => 'Jorge Amado', 'cidade' => 'Itabuna', 'nascimento' => 1912],
        ['nome' => 'Clarice Lispector', 'cidade' => 'Chechelnyk', 'nascimento' => 1920],
    ];

    public function __construct(
        private WebViewRenderer $viewRenderer,
        private Catalogo $catalogo,
    ) {}

    public function __invoke(
        #[Query('busca')] string $busca = '',
        #[Query('autor')] string $autor = '',
        #[Query('preco_max')] string $precoMaximo = '',
    ): ResponseInterface {
        $filtros = [];

        if ($busca !== '') {
            $filtros[] = new Like('titulo', $busca);
        }
        if (ctype_digit($autor)) {
            $filtros[] = new Equals('autorId', (int) $autor);
        }
        if (is_numeric($precoMaximo)) {
            $filtros[] = new LessThanOrEqual('preco', (float) $precoMaximo);
        }

        $livros = $this->catalogo->livros()->withFilter(new AndX(...$filtros));

        $nascimentos = (new IterableDataReader(self::NASCIMENTOS))
            ->withSort(Sort::only(['nascimento', 'nome'])->withOrderString('-nascimento'));

        if ($busca !== '') {
            $nascimentos = $nascimentos->withFilter(new Like('nome', $busca));
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'busca' => $busca,
            'autor' => $autor,
            'precoMaximo' => $precoMaximo,
            'autores' => $this->catalogo->autores(),
            'livros' => $livros->read(),
            'total' => $livros->count(),
            'nascimentos' => $nascimentos->read(),
        ]);
    }
}
```

Cada campo do formulário só vira filtro se tiver um valor aproveitável. Os parâmetros chegam como string e são validados antes: `ctype_digit()` para o id do autor, `is_numeric()` para o preço. Um campo vazio ou com lixo simplesmente não filtra. Um `AndX` sem nenhum filtro dentro não acrescenta condição: com o formulário vazio, aparecem todos os livros.

Para um formulário com mais campos e regras, vale usar um `FormModel` com validação, como no capítulo [Validação](/formularios/validacao), e montar os filtros a partir dele.

## Lendo de um array

Na mesma action, a lista de autores com local e ano de nascimento não está no banco: é a constante `NASCIMENTOS`. O `IterableDataReader` oferece para ela a mesma interface do leitor do banco. O `Sort` ordena do nascimento mais recente para o mais antigo, e o mesmo texto da busca filtra pelo nome com `Like`.

O `IterableDataReader` faz tudo em PHP: percorre os itens, aplica o filtro, ordena e corta. Isso serve bem para listas pequenas ou que já estão na memória. Para muitos itens vindos de um banco, filtrar no SQL com o `QueryDataReader` é sempre melhor que carregar tudo e filtrar depois. Por padrão, o `Like` do `IterableDataReader` não diferencia maiúsculas de minúsculas, inclusive em letras acentuadas, porque usa `mb_stripos()`. Mas "a" e "á" continuam sendo letras diferentes.

Por padrão, cada item é um array e o campo é uma chave. Para objetos ou campos aninhados (`autor.nome`), passe outro leitor de valores no segundo argumento do construtor, como o `PathValueReader`.

## A view

A view, `src/Web/Dados/Filtros/template.php`, tem o formulário e as duas listas:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $busca
 * @var string $autor
 * @var string $precoMaximo
 * @var array<string, string> $autores
 * @var iterable<array> $livros
 * @var int $total
 * @var iterable<array> $nascimentos
 */

$this->setTitle('Busca no catálogo');
?>

<h1>Busca no catálogo</h1>

<form method="get">
    <?= Html::textInput('busca', $busca, ['placeholder' => 'Texto']) ?>
    <?= Html::select('autor')->optionsData($autores)->prompt('Todos os autores')->value($autor) ?>
    <?= Html::input('number', 'preco_max', $precoMaximo, ['step' => '0.01', 'placeholder' => 'Preço máximo']) ?>
    <?= Html::submitButton('Buscar') ?>
</form>

<h2><?= $total ?> livro(s)</h2>
<ul>
    <?php foreach ($livros as $livro): ?>
        <li><?= Html::encode($livro['titulo']) ?>, de <?= Html::encode($livro['autor']) ?> (R$ <?= Html::encode($livro['preco']) ?>)</li>
    <?php endforeach ?>
</ul>

<h2>Autores</h2>
<ul>
    <?php foreach ($nascimentos as $pessoa): ?>
        <li><?= Html::encode($pessoa['nome']) ?>, nascido(a) em <?= Html::encode($pessoa['cidade']) ?> em <?= $pessoa['nascimento'] ?></li>
    <?php endforeach ?>
</ul>
```

O `select` de autores usa o array `[id => nome]` que `Catalogo::autores()` monta com `indexBy('id')` e `column()`.

A rota:

```php
Route::get('/dados/filtros')
    ->action(Web\Dados\Filtros\Action::class)
    ->name('dados/filtros'),
```

Testando:

- `/dados/filtros` mostra "12 livro(s)" e os cinco autores, de Clarice Lispector (1920) a Machado de Assis (1839).
- `/dados/filtros?autor=3&preco_max=70` mostra 2 livros de Jorge Amado: "Capitães da Areia" e "Gabriela, Cravo e Canela". "Dona Flor e Seus Dois Maridos", de 74,90, fica de fora.
- `/dados/filtros?busca=ma` mostra "Dona Flor e Seus Dois Maridos" e "Memórias Póstumas de Brás Cubas", e na lista de autores, Jorge Amado e Machado de Assis.
- `/dados/filtros?busca=&autor=&preco_max=`, que é o que o formulário envia vazio, mostra tudo.

## Comparando com o Yii2

- O papel do `ActiveDataProvider` e do `ArrayDataProvider` do Yii2 é feito pelo `QueryDataReader` e pelo `IterableDataReader`. A paginação e a ordenação não ficam dentro do leitor como `pagination` e `sort`; são objetos separados que se aplicam a ele.
- Os filtros do Yii2 eram condições do query builder, presas ao banco. Aqui, os filtros são objetos independentes do armazenamento, e cada leitor traduz para o seu formato.
