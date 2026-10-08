# Active Record

No query builder, cada linha volta como array. Com Active Record, cada linha vira um objeto de uma classe sua, que sabe se gravar, se apagar e buscar os registros relacionados. No Yii3 isso fica no pacote opcional `yiisoft/active-record`. Este capítulo mostra como declarar os modelos, consultar, gravar, definir relações e quando vale a pena usar.

## Instalando

```bash
composer require yiisoft/active-record
```

O pacote precisa de uma conexão, mas não recebe ela pelo construtor: os modelos são criados com `new`, fora do container. Em vez disso, ele usa o `ConnectionProvider` do `yiisoft/db`, um registro estático de conexões. O próprio pacote traz um arquivo de bootstrap que, quando a aplicação sobe, pega a `ConnectionInterface` do container e registra como conexão padrão. Por isso, com a conexão configurada como em [Conexão e comandos](/banco-de-dados/conexao), não há nada para configurar.

Se você tiver mais de um banco, registre os outros com um nome, `ConnectionProvider::set($outraConexao, 'relatorios')`, e sobrescreva o método `db()` no modelo que usa esse banco para devolver `ConnectionProvider::get('relatorios')`.

## Declarando um modelo

Um modelo estende `ActiveRecord` e declara uma propriedade para cada coluna. Crie `src/BancoDeDados/Livro.php`:

```php
<?php

declare(strict_types=1);

namespace App\BancoDeDados;

use DateTimeImmutable;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class Livro extends ActiveRecord
{
    public int $id;
    public int $autor_id;
    public string $titulo;
    public string $slug;
    public int $ano;
    public string $preco;
    public DateTimeImmutable $cadastrado_em;

    public function tableName(): string
    {
        return 'livro';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'autor' => $this->getAutorQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getAutor(): Autor
    {
        return $this->relation('autor');
    }

    public function getAutorQuery(): ActiveQuery
    {
        return $this->hasOne(Autor::class, ['id' => 'autor_id']);
    }
}
```

Por partes:

::: v-pre
- As propriedades têm o mesmo nome das colunas. Ao carregar um registro, o Active Record converte cada valor para o tipo da coluna: `integer` vira `int` e `datetime` vira `DateTimeImmutable`. A coluna `preco` é `decimal` e chega como string, para não perder precisão. Se você declarar `float $preco`, a leitura falha com um `TypeError`.
- `tableName()` diz qual é a tabela. Sem ele, o nome sai da classe: `Livro` vira `{{%livro}}`, e uma classe `ItemPedido` viraria `{{%item_pedido}}`.
- A parte de relações é explicada mais adiante.
:::

E `src/BancoDeDados/Autor.php`:

```php
<?php

declare(strict_types=1);

namespace App\BancoDeDados;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class Autor extends ActiveRecord
{
    public int $id;
    public string $nome;

    public function tableName(): string
    {
        return 'autor';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'livros' => $this->getLivrosQuery(),
            default => parent::relationQuery($name),
        };
    }

    /**
     * @return Livro[]
     */
    public function getLivros(): array
    {
        return $this->relation('livros');
    }

    public function getLivrosQuery(): ActiveQuery
    {
        return $this->hasMany(Livro::class, ['autor_id' => 'id'])->orderBy(['ano' => SORT_ASC]);
    }
}
```

## Consultando

O método estático `query()` devolve um `ActiveQuery`, que tem os mesmos métodos do query builder (`where()`, `orderBy()`, `limit()`…). A diferença é que `all()` e `one()` devolvem objetos do modelo:

```php
$livro = Livro::query()->findByPk(3);
$livro->titulo;                  // 'Dom Casmurro'
$livro->cadastrado_em;           // DateTimeImmutable

$recentes = Livro::query()
    ->where(['>', 'ano', 1960])
    ->orderBy('ano')
    ->all();                     // [Livro, Livro, Livro]

Livro::query()->where(['slug' => 'nao-existe'])->one(); // null
```

`findByPk()` busca pela chave primária e devolve `null` se não achar. Para um relatório em que você não precisa de objetos, `asArray()` faz a consulta devolver arrays, como no query builder.

## Gravando e apagando

Para inserir, crie o objeto, preencha as propriedades e chame `save()`. Depois do insert, a chave gerada já está no objeto:

```php
$autor = new Autor();
$autor->nome = 'Cecília Meireles';
$autor->save();

$livro = new Livro();
$livro->autor_id = $autor->id;
$livro->titulo = 'Romanceiro da Inconfidência';
$livro->slug = 'romanceiro-da-inconfidencia';
$livro->ano = 1953;
$livro->preco = '52.90';
$livro->cadastrado_em = new DateTimeImmutable();
$livro->save();
```

Num objeto carregado do banco, `save()` faz um `UPDATE` só com as colunas que mudaram. `isChanged()` diz se algo mudou e `newValues()` mostra o quê:

```php
$livro = Livro::query()->where(['slug' => 'romanceiro-da-inconfidencia'])->one();
$livro->preco = '47.90';
$livro->newValues();   // ['preco' => '47.90']
$livro->save();

$livro->delete();
$autor->delete();
```

Esses trechos foram executados em sequência, e o SQL gerado foi:

```plaintext
INSERT INTO "autor" ("nome") VALUES ('Cecília Meireles') RETURNING "id"
INSERT INTO "livro" ("autor_id", "titulo", "slug", "ano", "preco", "cadastrado_em") VALUES (5, 'Romanceiro da Inconfidência', ...) RETURNING "id"
SELECT * FROM "livro" WHERE "slug" = 'romanceiro-da-inconfidencia'
UPDATE "livro" SET "preco"='47.90' WHERE "id" = 13
DELETE FROM "livro" WHERE "id" = 13
DELETE FROM "autor" WHERE "id" = 5
```

O modelo não valida nada antes de gravar. No Yii3, validação é papel do formulário (veja [Validação](/formularios/validacao)): você valida os dados de entrada e só então passa para o modelo.

## Relações

Uma relação é uma consulta que parte do registro atual. Ela é declarada com dois métodos:

- `hasOne(Classe::class, ['coluna_na_outra' => 'coluna_aqui'])`, quando existe no máximo um relacionado. Um livro tem um autor: `['id' => 'autor_id']` liga `autor.id` a `livro.autor_id`.
- `hasMany(...)`, quando podem existir vários. Um autor tem vários livros: `['autor_id' => 'id']`.

O modelo precisa dizer, em `relationQuery()`, qual consulta corresponde a cada nome de relação. Com isso, `relation('livros')` executa a consulta na primeira chamada e guarda o resultado no objeto. Os métodos `getLivros()` e `getAutor()` só existem para dar um tipo de retorno ao editor e ao Psalm.

Se preferir não escrever o `match`, use o trait `MagicRelationsTrait`: ele encontra a relação `livros` procurando o método `getLivrosQuery()`.

O método `get*Query()` também serve sozinho, quando você quer filtrar os relacionados em vez de carregar todos:

```php
$autor = Autor::query()->findByPk(2);
$autor->getLivrosQuery()->where(['>', 'ano', 1960])->count(); // 2
```

Para relações muitos-para-muitos, com tabela de ligação, o `ActiveQuery` tem `viaTable()` e `via()`.

### Carregando relações de uma vez

Esta action lista os autores com os livros de cada um. Crie `src/Web/BancoDeDados/Autores/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Autores;

use App\BancoDeDados\Autor;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $autores = Autor::query()
            ->with('livros')
            ->orderBy(['nome' => SORT_ASC])
            ->all();

        return $this->viewRenderer->render(__DIR__ . '/template', ['autores' => $autores]);
    }
}
```

O `with('livros')` é o que importa aqui. Sem ele, cada `getLivros()` na view dispara uma consulta, e a página faz 5 consultas para 4 autores (o problema conhecido como N+1):

```plaintext
SELECT * FROM "autor" ORDER BY "nome"
SELECT * FROM "livro" WHERE "autor_id"=2 ORDER BY "ano"
SELECT * FROM "livro" WHERE "autor_id"=4 ORDER BY "ano"
SELECT * FROM "livro" WHERE "autor_id"=3 ORDER BY "ano"
SELECT * FROM "livro" WHERE "autor_id"=1 ORDER BY "ano"
```

Com `with('livros')`, são sempre duas, não importa quantos autores:

```plaintext
SELECT * FROM "autor" ORDER BY "nome"
SELECT * FROM "livro" WHERE "autor_id" IN (2, 4, 3, 1) ORDER BY "ano"
```

A view, em `src/Web/BancoDeDados/Autores/template.php`, percorre os objetos:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var App\BancoDeDados\Autor[] $autores
 */

$this->setTitle('Autores');
?>

<h1>Autores</h1>

<?php foreach ($autores as $autor): ?>
    <h2><?= Html::encode($autor->nome) ?></h2>
    <ul>
        <?php foreach ($autor->getLivros() as $livro): ?>
            <li>
                <?= Html::a(
                    Html::encode($livro->titulo),
                    $urlGenerator->generate('banco-de-dados/livro', ['id' => $livro->id]),
                ) ?>
                (<?= $livro->ano ?>)
            </li>
        <?php endforeach ?>
    </ul>
<?php endforeach ?>
```

Para filtrar pela tabela relacionada, use `joinWith()`. Ele faz o `JOIN` e, por padrão, também carrega a relação:

```php
Livro::query()
    ->joinWith('autor')
    ->where(['autor.nome' => 'Jorge Amado'])
    ->orderBy(['livro.ano' => SORT_DESC])
    ->all();
```

```plaintext
SELECT "livro".* FROM "livro" LEFT JOIN "autor" ON "livro"."autor_id" = "autor"."id" WHERE "autor"."nome" = 'Jorge Amado' ORDER BY "livro"."ano" DESC
SELECT * FROM "autor" WHERE "id"=3
```

### A página de um livro

A action de detalhe busca pela chave primária e devolve 404 quando o livro não existe. Crie `src/Web/BancoDeDados/Livro/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Livro;

use App\BancoDeDados\Livro;
use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private NotFoundHandler $notFoundHandler,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[RouteArgument('id')] int $id): ResponseInterface
    {
        $livro = Livro::query()->findByPk($id);

        if ($livro === null) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/template', ['livro' => $livro]);
    }
}
```

A view (`src/Web/BancoDeDados/Livro/template.php`) mostra o título, `$livro->getAutor()->nome`, o ano, o preço e `$livro->cadastrado_em->format('d/m/Y H:i')`. As rotas das duas páginas:

```php
Route::get('/banco-de-dados/livros/{id:\d+}')
    ->action(Web\BancoDeDados\Livro\Action::class)
    ->name('banco-de-dados/livro'),
Route::get('/banco-de-dados/autores')
    ->action(Web\BancoDeDados\Autores\Action::class)
    ->name('banco-de-dados/autores'),
```

`/banco-de-dados/autores` lista Clarice Lispector, Graciliano Ramos, Jorge Amado e Machado de Assis, cada um com três livros em ordem de publicação. O link de "Dom Casmurro" leva a `/banco-de-dados/livros/3`, com autor Machado de Assis, ano 1899 e "Cadastrado em: 02/02/2026 10:00". Já `/banco-de-dados/livros/99` devolve 404.

## Quando usar e quando evitar

Active Record encaixa bem em telas de cadastro: carregar um registro, mudar alguns campos, gravar. As relações com `with()` também economizam código em páginas que mostram um registro e os seus relacionados.

Ele pesa mais quando:

- A regra de negócio é grande. O modelo mistura dados, persistência e consulta, e fica difícil testar a regra sem banco. Nesses casos, entidades simples mais um repository costumam funcionar melhor (veja [Domínio: entidades e serviços](/estrutura/dominio)).
- A consulta é um relatório, com agregações e muitas linhas. Criar um objeto por linha custa memória e tempo, e o query builder (ou `asArray()`) resolve com menos.
- Você precisa de vários bancos ou de conexões trocadas em tempo de execução, já que a conexão vem de um registro estático.

Nada impede usar os dois no mesmo projeto: Active Record nos cadastros e query builder nas listagens e relatórios.

## Comparando com o Yii2

- O pacote é opcional e separado do framework. Não existe uma classe `Model` por trás: o modelo não valida nem tem `rules()`, `load()` ou cenários.
- `Livro::find()` virou `Livro::query()`. Os atalhos estáticos `find()`, `findOne()` e `findAll()` existem no trait `RepositoryTrait`, se você quiser.
- `tableName()` é um método de instância, não estático.
- As colunas são propriedades declaradas na classe, com tipo. Se você prefere o estilo antigo, o trait `MagicPropertiesTrait` permite acessar colunas e relações como propriedades mágicas.
- No Yii2, `getLivros()` devolvia a consulta e `$autor->livros` carregava os dados. Aqui, a consulta fica em `getLivrosQuery()` e os dados em `relation('livros')`.
