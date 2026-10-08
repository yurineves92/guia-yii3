# Query builder

Escrever SQL à mão funciona, mas fica trabalhoso quando a consulta muda conforme a entrada do usuário: um filtro que só entra se o campo foi preenchido, uma ordenação escolhida na tela. O query builder monta o SQL com métodos PHP, cuida das aspas e dos parâmetros, e gera o dialeto certo para o banco da conexão. Este capítulo mostra as consultas, as condições e os comandos de escrita.

Os exemplos estão em `src/BancoDeDados/LivroRepository.php`, uma classe que recebe a conexão no construtor e concentra as consultas da tabela `livro`:

```php
final readonly class LivroRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    // métodos mostrados ao longo do capítulo
}
```

## Montando um SELECT

`$db->select()` cria um objeto `Query` já com as colunas. A partir dele, cada método acrescenta uma parte da consulta. Nada é executado até você pedir os resultados. Este é o método `comAutor()` do repositório:

```php
public function comAutor(): QueryInterface
{
    return $this->db
        ->select(['l.id', 'l.titulo', 'l.ano', 'l.preco', 'autor' => 'a.nome'])
        ->from(['l' => 'livro'])
        ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id')
        ->orderBy(['a.nome' => SORT_ASC, 'l.ano' => SORT_ASC]);
}
```

- Em `select()` e `from()`, uma chave de texto no array vira alias: `'autor' => 'a.nome'` gera `"a"."nome" AS "autor"`.
- `innerJoin()` recebe a tabela e a condição de junção. Também existem `leftJoin()`, `rightJoin()` e `join()`, onde você informa o tipo.
- `orderBy()` recebe coluna e direção com as constantes `SORT_ASC` e `SORT_DESC`. Para acrescentar critérios depois, use `addOrderBy()`.

Para conferir o que foi montado, peça o comando e o SQL:

```php
$this->comAutor()->createCommand()->getRawSql();
```

```plaintext
SELECT "l"."id", "l"."titulo", "l"."ano", "l"."preco", "a"."nome" AS "autor" FROM "livro" "l"
INNER JOIN "autor" "a" ON a.id = l.autor_id ORDER BY "a"."nome", "l"."ano"
```

Os nomes de tabela e coluna ganham as aspas do banco automaticamente. A condição do `JOIN`, escrita como string, vai do jeito que está.

### Buscando os resultados

Os métodos que executam a consulta são:

- `all()`: todas as linhas, como arrays associativos.
- `one()`: a primeira linha ou `null`.
- `column()`: a primeira coluna de cada linha.
- `scalar()`: o primeiro valor da primeira linha.
- `exists()`: `true` se houver pelo menos uma linha.
- `count()`, `sum()`, `average()`, `min()` e `max()`: funções de agregação, por exemplo `$db->select()->from('livro')->count()`, que devolve `12`.

`limit()` e `offset()` cortam o resultado, e `indexBy('id')` faz o `all()` devolver o array indexado pela coluna `id` em vez de 0, 1, 2. Assim como nos comandos, os valores chegam como string no SQLite. Com `withTypecasting()`, a consulta converte os valores conforme o tipo da coluna: `id` e `ano` viram `int`, e `preco`, que é `decimal`, continua string.

Um cuidado: diferente da maioria dos objetos do Yii3, a `Query` é mutável. `where()`, `orderBy()` e os outros alteram o próprio objeto e devolvem ele mesmo. Se for reaproveitar uma consulta-base, crie uma nova a cada uso, como o repositório faz chamando `comAutor()`, ou use `clone`.

## Condições

O `where()` aceita condições em três formatos.

O formato de hash compara colunas com valores. Vários pares viram `AND`, um array vira `IN` e `null` vira `IS NULL`:

```php
->where(['autor_id' => 1, 'ano' => 1899])
// WHERE ("autor_id" = 1) AND ("ano" = 1899)

->where(['autor_id' => [1, 2]])
// WHERE "autor_id" IN (1, 2)
```

O formato de operador é um array em que o primeiro item é o operador:

```php
->where(['>=', 'ano', 1950])
// WHERE "ano" >= 1950

->where(['between', 'ano', 1930, 1940])
// WHERE "ano" BETWEEN 1930 AND 1940

->where(['like', 'titulo', 'Dona'])
// WHERE "titulo" LIKE '%Dona%' ESCAPE '\'

->where(['in', 'slug', ['dom-casmurro', 'vidas-secas']])
// WHERE "slug" IN ('dom-casmurro', 'vidas-secas')

->where(['not', ['autor_id' => 1]])
// WHERE "autor_id" <> 1

->where(['or', ['autor_id' => 4], ['<', 'preco', 35]])
// WHERE ("autor_id" = 4) OR ("preco" < 35)
```

O `like` coloca os `%` e escapa os caracteres especiais do valor. Também existem `not like`, `or like`, `not between`, `not in`, `exists` e `not exists`, e qualquer operador de comparação (`=`, `<>`, `<`, `>`…).

O terceiro formato são objetos de condição, do namespace `Yiisoft\Db\QueryBuilder\Condition`. Eles geram o mesmo SQL e ajudam o editor a completar os argumentos:

```php
use Yiisoft\Db\QueryBuilder\Condition\Between;
use Yiisoft\Db\QueryBuilder\Condition\GreaterThan;

->where(new GreaterThan('ano', 1950))
// WHERE "ano" > 1950

->where(new Between('ano', 1930, 1940))
// WHERE "ano" BETWEEN 1930 AND 1940
```

Por fim, uma string com SQL, sempre com os valores em parâmetros:

```php
->where('ano > :ano', [':ano' => 1960])
```

### Combinando condições

`andWhere()` e `orWhere()` acrescentam condições às que já existem:

```php
->where(['autor_id' => 3])
->andWhere(['>', 'preco', 60])
->orWhere(['slug' => 'dom-casmurro'])
// WHERE (("autor_id" = 3) AND ("preco" > 60)) OR ("slug" = 'dom-casmurro')
```

Chamar `where()` duas vezes na mesma consulta lança `LogicException`, para você não apagar uma condição sem perceber. Se a intenção é mesmo trocar a condição, use `setWhere()`.

### Filtros opcionais

Num formulário de busca, cada campo pode vir vazio. Em vez de encher o código de `if`, use `filterWhere()`, `andFilterWhere()` ou `orFilterWhere()`: eles ignoram as condições cujo valor é `null`, array vazio ou string vazia (ou só com espaços). É o que faz o método `buscar()`:

```php
public function buscar(?string $termo, ?float $precoMaximo): QueryInterface
{
    return $this->comAutor()
        ->andFilterWhere(['like', 'l.titulo', $termo])
        ->andFilterWhere(['<=', 'l.preco', $precoMaximo]);
}
```

Com `buscar('da', 50.0)`, as duas condições entram:

```plaintext
... WHERE ("l"."titulo" LIKE '%da%' ESCAPE '\') AND ("l"."preco" <= 50) ORDER BY ...
```

Com `buscar(null, null)`, o SQL fica igual ao de `comAutor()`, sem `WHERE`.

## Agrupando

`groupBy()` e `having()` funcionam como no SQL. O `having()` aceita os mesmos formatos de condição do `where()`:

```php
public function totaisPorAutor(): QueryInterface
{
    return $this->db
        ->select(['autor' => 'a.nome', 'livros' => 'COUNT(*)', 'preco_medio' => 'AVG(l.preco)'])
        ->from(['l' => 'livro'])
        ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id')
        ->groupBy('a.nome')
        ->having(['>', 'COUNT(*)', 2])
        ->orderBy(['preco_medio' => SORT_DESC]);
}
```

```plaintext
SELECT "a"."nome" AS "autor", COUNT(*) AS "livros", AVG(l.preco) AS "preco_medio" FROM "livro" "l"
INNER JOIN "autor" "a" ON a.id = l.autor_id GROUP BY "a"."nome" HAVING COUNT(*) > 2 ORDER BY "preco_medio" DESC
```

Uma coluna com parênteses, como `COUNT(*)`, é tratada como expressão e não recebe aspas.

## A página de busca

A action em `src/Web/BancoDeDados/Livros/Action.php` recebe os filtros da URL e usa o repositório:

```php
<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Livros;

use App\BancoDeDados\LivroRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private LivroRepository $livros,
    ) {}

    public function __invoke(
        #[Query('busca')] ?string $busca = null,
        #[Query('preco_max')] ?string $precoMaximo = null,
    ): ResponseInterface {
        $preco = is_numeric($precoMaximo) ? (float) $precoMaximo : null;

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'busca' => $busca,
            'precoMaximo' => $preco,
            'livros' => $this->livros->buscar($busca, $preco)->all(),
            'totais' => $this->livros->totaisPorAutor()->all(),
        ]);
    }
}
```

O preço chega como string e só vira número se for numérico. Assim, um campo enviado vazio (`?preco_max=`) vira `null` e o filtro é ignorado, em vez de virar `0` e esconder todos os livros. O `LivroRepository` não precisa de configuração no container: o autowiring entrega a conexão para ele.

A view, em `src/Web/BancoDeDados/Livros/template.php`, tem um formulário GET com os campos `busca` e `preco_max`, uma tabela com os livros e uma lista com os totais por autor. A rota:

```php
Route::get('/banco-de-dados/livros')
    ->action(Web\BancoDeDados\Livros\Action::class)
    ->name('banco-de-dados/livros'),
```

Em `/banco-de-dados/livros` aparecem os 12 livros. Em `/banco-de-dados/livros?busca=da&preco_max=50`, só "A Hora da Estrela" e "Vidas Secas". Os totais mostram os quatro autores, com Jorge Amado no topo e preço médio de 68,23.

## Inserir, atualizar, apagar e upsert

A escrita usa os métodos do comando, sem `Query`. Cada um monta o SQL a partir de arrays e só roda no `execute()`, que devolve o número de linhas afetadas. Os trechos abaixo são do `LivroRepository`, com o SQL que cada um gerou.

Inserir:

```php
public function inserir(int $autorId, string $titulo, string $slug, int $ano, string $preco): int
{
    $this->db->createCommand()->insert('livro', [
        'autor_id' => $autorId,
        'titulo' => $titulo,
        'slug' => $slug,
        'ano' => $ano,
        'preco' => $preco,
        'cadastrado_em' => date('Y-m-d H:i:s'),
    ])->execute();

    return (int) $this->db->getLastInsertId();
}
```

```plaintext
INSERT INTO "livro" ("autor_id", "titulo", "slug", "ano", "preco", "cadastrado_em")
VALUES (1, 'Esaú e Jacó', 'esau-e-jaco', 1904, '36.90', '2026-10-08 01:59:01')
```

Para várias linhas de uma vez, use `insertBatch($tabela, $linhas, $colunas)`, como na migration que popula as tabelas.

Atualizar recebe a tabela, as colunas novas e a condição, nos mesmos formatos do `where()`. Quando o novo valor depende do atual, use uma `Expression`, que entra no SQL sem aspas e pode ter parâmetros:

```php
public function reajustarPrecos(int $autorId, int $percentual): int
{
    return $this->db->createCommand()->update(
        'livro',
        ['preco' => new Expression('ROUND(preco * :fator, 2)', [':fator' => 1 + $percentual / 100])],
        ['autor_id' => $autorId],
    )->execute();
}
```

```plaintext
UPDATE "livro" SET "preco"=ROUND(preco * 1.1, 2) WHERE "autor_id" = 1
```

O upsert insere a linha ou, se ela já existir, atualiza. "Já existir" quer dizer bater numa chave primária ou única: aqui, a coluna `slug`. O terceiro argumento diz quais colunas atualizar no conflito. Passando `true`, atualiza todas as do insert; passando `false`, não atualiza nada.

```php
public function salvar(int $autorId, string $titulo, string $slug, int $ano, string $preco): void
{
    $this->db->createCommand()->upsert(
        'livro',
        [
            'autor_id' => $autorId,
            'titulo' => $titulo,
            'slug' => $slug,
            'ano' => $ano,
            'preco' => $preco,
            'cadastrado_em' => date('Y-m-d H:i:s'),
        ],
        ['titulo' => $titulo, 'preco' => $preco],
    )->execute();
}
```

No SQLite, isso gera um `INSERT ... ON CONFLICT`. Cada driver gera a sintaxe do seu banco, e o seu código não muda:

```plaintext
INSERT INTO "livro" (...) VALUES (1, 'Esaú e Jacó (edição revista)', 'esau-e-jaco', 1904, '38.00', ...)
ON CONFLICT ("slug") DO UPDATE SET "titulo"='Esaú e Jacó (edição revista)', "preco"='38.00'
```

Apagar recebe a tabela e a condição:

```php
public function apagar(string $slug): int
{
    return $this->db->createCommand()->delete('livro', ['slug' => $slug])->execute();
}
```

```plaintext
DELETE FROM "livro" WHERE "slug" = 'esau-e-jaco'
```

::: warning
`update()` e `delete()` sem condição afetam a tabela inteira. Não existe proteção contra isso: confira a condição, principalmente quando ela é montada a partir de dados da requisição.
:::

## Comparando com o Yii2

- Em vez de `(new Query())->from('livro')->all($db)`, a consulta nasce da conexão: `$db->select()->from('livro')->all()`. Se preferir instanciar, `new Query($db)` recebe a conexão no construtor.
- Os formatos de condição em hash e com operador são os mesmos. Os objetos de condição, como `GreaterThan` e `Between`, são novos.
- Chamar `where()` duas vezes lança exceção. No Yii2, a segunda chamada substituía a primeira em silêncio.
- `batchInsert()` virou `insertBatch()`, com as linhas antes das colunas. O `upsert()` já existia no Yii2 e continua aqui.
