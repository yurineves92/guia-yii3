# Conexão e comandos

Todo acesso a banco no Yii3 passa por um objeto que implementa `ConnectionInterface`, do pacote `yiisoft/db`. Este capítulo mostra como montar essa conexão para cada banco, como executar SQL com ela e como agrupar várias operações numa transação. O query builder e o Active Record, dos próximos capítulos, são camadas em cima do que está aqui.

Os exemplos usam as tabelas `autor` e `livro`, com quatro autores e doze livros. Elas são criadas no capítulo [Migrations](/banco-de-dados/migrations).

## Drivers

O `yiisoft/db` define as interfaces e a maior parte do comportamento, mas não fala com nenhum banco sozinho. Para isso existe um pacote por banco:

- `yiisoft/db-sqlite`: SQLite
- `yiisoft/db-mysql`: MySQL e MariaDB
- `yiisoft/db-pgsql`: PostgreSQL
- `yiisoft/db-mssql`: Microsoft SQL Server
- `yiisoft/db-oracle`: Oracle

Cada um traz as mesmas três peças, no seu namespace: `Driver` (cria o PDO), `Connection` (a implementação de `ConnectionInterface`) e um `Dsn` para montar a string de conexão. O app de exemplo usa SQLite, configurado em `config/common/di/db.php` como mostrado em [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados):

```php
ConnectionInterface::class => static fn (Aliases $aliases, SchemaCache $schemaCache) => new Connection(
    new Driver('sqlite:' . $aliases->get('@runtime/database.sqlite')),
    $schemaCache,
),
```

O primeiro argumento do `Driver` é o DSN do PDO, como string ou como objeto `Dsn`. Para SQLite, `new Dsn('sqlite', $caminho)` gera a mesma string `sqlite:/caminho/do/arquivo`, e `new Dsn('sqlite', 'memory')` cria um banco em memória.

Para MySQL ou PostgreSQL, instale o pacote correspondente e troque as classes. O `Driver` recebe usuário e senha depois do DSN:

```php
use Yiisoft\Db\Pgsql\Connection;
use Yiisoft\Db\Pgsql\Driver;

ConnectionInterface::class => static fn (SchemaCache $schemaCache) => new Connection(
    new Driver('pgsql:host=127.0.0.1;dbname=livraria', 'usuario', 'senha'),
    $schemaCache,
),
```

Esse trecho não roda no app de exemplo, que só tem o `yiisoft/db-sqlite` instalado. Em produção, leia usuário e senha de variáveis de ambiente em vez de deixar no arquivo.

O `SchemaCache` guarda a estrutura das tabelas (colunas, tipos, chaves) para a conexão não consultar o banco toda vez. No exemplo ele usa `ArrayCache`, que só vale durante uma requisição. Em produção, faz mais sentido um cache persistente, como o de arquivo do `yiisoft/cache-file`. O capítulo de [Cache](/cache/visao-geral) mostra como configurar um.

A conexão é preguiçosa: criar o objeto não abre conexão nenhuma. Ela é aberta no primeiro comando executado.

## Executando SQL com createCommand()

Com a conexão em mãos, `createCommand()` recebe o SQL e os parâmetros e devolve um `CommandInterface`. O comando não é executado até você chamar um dos métodos `query*()` ou `execute()`.

Crie `src/Web/BancoDeDados/Comandos/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Comandos;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $total = $this->db
            ->createCommand('SELECT COUNT(*) FROM livro')
            ->queryScalar();

        $autores = $this->db
            ->createCommand('SELECT nome FROM autor ORDER BY nome')
            ->queryColumn();

        $antigos = $this->db
            ->createCommand('SELECT titulo, ano FROM livro WHERE ano < :ano ORDER BY ano', [':ano' => 1900])
            ->queryAll();

        $livro = $this->db
            ->createCommand('SELECT * FROM {{livro}} WHERE [[slug]] = :slug')
            ->bindValue(':slug', 'dom-casmurro')
            ->queryOne();

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'driver' => $this->db->getDriverName(),
            'total' => $total,
            'autores' => $autores,
            'antigos' => $antigos,
            'livro' => $livro,
        ]);
    }
}
```

Cada método de leitura devolve os dados num formato:

- `queryAll()`: todas as linhas, cada uma como array associativo.
- `queryOne()`: a primeira linha, ou `null` se não houver nenhuma.
- `queryColumn()`: a primeira coluna de todas as linhas, como lista.
- `queryScalar()`: o valor da primeira coluna da primeira linha. Bom para `COUNT`, `MAX` e parecidos.
- `query()`: um leitor que percorre as linhas uma a uma, sem carregar tudo na memória.

Para `INSERT`, `UPDATE`, `DELETE` e comandos de estrutura, use `execute()`, que devolve o número de linhas afetadas.

::: v-pre
Valores que vêm de fora nunca devem ser concatenados no SQL. Passe como parâmetro, no segundo argumento de `createCommand()` ou com `bindValue()`, e o PDO cuida do escape. Os nomes entre `{{ }}` são tabelas e os entre `[[ ]]` são colunas: a conexão troca pelas aspas certas do banco em uso. Escrevendo `{{%livro}}`, o `%` é substituído pelo prefixo de tabelas definido com `setTablePrefix()`.
:::

Para ver o SQL final, com os parâmetros já substituídos, use `getRawSql()`. Ajuda bastante a depurar:

```php
$this->db->createCommand('SELECT * FROM {{%livro}} WHERE [[ano]] < :ano', [':ano' => 1900])->getRawSql();
// SELECT * FROM "livro" WHERE "ano" < 1900
```

A view, em `src/Web/BancoDeDados/Comandos/template.php`, só exibe os valores:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $driver
 * @var int|string $total
 * @var string[] $autores
 * @var array[] $antigos
 * @var array|null $livro
 */

$this->setTitle('Comandos');
?>

<h1>Comandos</h1>

<p>Driver: <?= Html::encode($driver) ?>. Livros cadastrados: <?= Html::encode($total) ?>.</p>

<p>Autores: <?= Html::encode(implode(', ', $autores)) ?></p>

<h2>Publicados antes de 1900</h2>
<ul>
    <?php foreach ($antigos as $antigo): ?>
        <li><?= Html::encode($antigo['titulo']) ?> (<?= Html::encode($antigo['ano']) ?>)</li>
    <?php endforeach ?>
</ul>

<?php if ($livro !== null): ?>
    <p>Pelo slug: <?= Html::encode($livro['titulo']) ?>, R$ <?= Html::encode($livro['preco']) ?></p>
<?php endif ?>
```

No arquivo de rotas:

```php
Route::get('/banco-de-dados/comandos')
    ->action(Web\BancoDeDados\Comandos\Action::class)
    ->name('banco-de-dados/comandos'),
```

Abrindo `/banco-de-dados/comandos`, a página mostra o driver `sqlite`, 12 livros, os quatro autores em ordem alfabética, os três livros de Machado de Assis publicados antes de 1900 e a linha "Pelo slug: Dom Casmurro, R$ 29.9".

### Tipos dos valores

Repare no `29.9`. O PDO do SQLite devolve tudo como string, inclusive `id` e `ano`. Se você quer os tipos do PHP, peça ao comando com `withPhpTypecasting()`:

```php
$this->db->createCommand('SELECT id, ano, preco FROM livro WHERE id = 1')
    ->withPhpTypecasting()
    ->queryOne();
// ['id' => 1, 'ano' => 1881, 'preco' => '34.9']
```

`id` e `ano` viram `int`. A coluna `preco` é `decimal` e continua como string, de propósito: converter para `float` pode perder precisão. Na hora de exibir, quem formata é a view, como mostra o capítulo [Formatação](/dados/formatacao).

## Transações

Uma transação garante que um grupo de comandos seja gravado inteiro ou não seja gravado. O jeito mais simples é passar uma closure para `transaction()`: se ela terminar normalmente, a conexão faz o commit; se lançar uma exceção, a conexão faz o rollback e relança a exceção.

Crie o serviço `src/BancoDeDados/CadastroDeAutor.php`, que grava um autor e os livros dele:

```php
<?php

declare(strict_types=1);

namespace App\BancoDeDados;

use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class CadastroDeAutor
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    /**
     * Grava o autor e os livros dele numa transação só.
     *
     * @param list<array{titulo: string, slug: string, ano: int, preco: string}> $livros
     */
    public function cadastrar(string $nome, array $livros): int
    {
        return $this->db->transaction(function (ConnectionInterface $db) use ($nome, $livros): int {
            $db->createCommand()->insert('autor', ['nome' => $nome])->execute();
            $autorId = (int) $db->getLastInsertId();

            foreach ($livros as $livro) {
                $db->createCommand()->insert('livro', [
                    ...$livro,
                    'autor_id' => $autorId,
                    'cadastrado_em' => date('Y-m-d H:i:s'),
                ])->execute();
            }

            return $autorId;
        });
    }

    /**
     * O mesmo que cadastrar(), controlando a transação na mão.
     *
     * @param list<array{titulo: string, slug: string, ano: int, preco: string}> $livros
     */
    public function cadastrarSemClosure(string $nome, array $livros): int
    {
        $transaction = $this->db->beginTransaction();

        try {
            $this->db->createCommand()->insert('autor', ['nome' => $nome])->execute();
            $autorId = (int) $this->db->getLastInsertId();

            foreach ($livros as $livro) {
                $this->db->createCommand()->insert('livro', [
                    ...$livro,
                    'autor_id' => $autorId,
                    'cadastrado_em' => date('Y-m-d H:i:s'),
                ])->execute();
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $autorId;
    }
}
```

O `createCommand()->insert()` monta o `INSERT` a partir de um array de colunas e valores, e o `getLastInsertId()` devolve o id gerado. O próximo capítulo detalha esses métodos. O valor de retorno da closure é o valor de retorno de `transaction()`.

O segundo método faz a mesma coisa com `beginTransaction()`, `commit()` e `rollBack()`. Use essa forma quando o fluxo não cabe bem numa closure. A primeira é mais curta e não deixa você esquecer o rollback.

Para ver o rollback acontecer, a action em `src/Web/BancoDeDados/Transacao/Action.php` tenta cadastrar uma autora com dois livros, sendo que o segundo repete o slug `dom-casmurro`, que já existe e é único na tabela:

```php
try {
    // O segundo livro repete um slug que já existe: o insert dele falha.
    $this->cadastro->cadastrar('Cecília Meireles', [
        ['titulo' => 'Romanceiro da Inconfidência', 'slug' => 'romanceiro', 'ano' => 1953, 'preco' => '52.90'],
        ['titulo' => 'Ou Isto ou Aquilo', 'slug' => 'dom-casmurro', 'ano' => 1964, 'preco' => '35.00'],
    ]);
} catch (IntegrityException $e) {
    $erro = $e->getMessage();
}
```

A action conta os autores antes e depois, e a página em `/banco-de-dados/transacao` mostra:

```plaintext
Autores antes: 4. Autores depois: 4.

O cadastro falhou e foi desfeito:
SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: livro.slug
The SQL being executed was: INSERT INTO "livro" ("titulo", "slug", "ano", "preco", "autor_id", "cadastrado_em") VALUES ...
```

O insert do autor e o do primeiro livro funcionaram, mas foram desfeitos junto com o terceiro comando. Violações de chave única ou estrangeira chegam como `Yiisoft\Db\Exception\IntegrityException`; outros erros de banco, como `Yiisoft\Db\Exception\Exception`.

Algumas observações:

- `transaction()` e `beginTransaction()` aceitam um nível de isolamento, com as constantes de `TransactionInterface` (`READ_COMMITTED`, `SERIALIZABLE` etc.). O SQLite só aceita `READ_UNCOMMITTED` e `SERIALIZABLE`.
- Uma transação aberta dentro de outra vira um savepoint. Um erro na interna desfaz só a parte dela, e o commit de verdade acontece quando a externa termina.
- Migrations que implementam `TransactionalMigrationInterface` já rodam dentro de uma transação, então você não precisa abrir outra lá.

## Comparando com o Yii2

::: v-pre
- O `Yii::$app->db` vira uma dependência `ConnectionInterface` no construtor. Não existe acesso global à conexão.
- A configuração é uma definição de container, com `Connection` e `Driver` do pacote do banco, em vez de um array com `dsn`, `username` e `password`.
- `createCommand()`, `queryAll()`, `queryOne()`, `queryColumn()`, `queryScalar()`, `execute()` e `transaction()` continuam com os mesmos nomes. A sintaxe `{{tabela}}` e `[[coluna]]` também.
:::