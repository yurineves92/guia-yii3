# Migrations

A estrutura do banco muda junto com o código: uma tabela nova, uma coluna a mais, um índice. Migrations são classes PHP que descrevem cada mudança e como desfazê-la, para que todo ambiente (o seu, o dos colegas, o de produção) chegue à mesma estrutura rodando um comando. Este capítulo cria as tabelas `autor` e `livro` usadas nos outros capítulos da seção e mostra os geradores, o `ColumnBuilder` e os comandos para reverter.

Os comandos `./yii migrate:*` vêm do pacote `yiisoft/db-migration`. A configuração mínima, com o namespace `App\Migration` e a pasta `src/Migration`, está em [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados#configurando-as-migrations). Em vez de namespace, você também pode trabalhar com caminhos de pasta, usando as chaves `newMigrationPath` e `sourcePaths` nos mesmos parâmetros.

## Os comandos

```plaintext
migrate:create   Creates a new migration.
migrate:down     Reverts the specified number of latest migrations.
migrate:history  Displays the migration history.
migrate:new      Displays not yet applied migrations.
migrate:redo     Redoes the last few migrations.
migrate:up       Applies new migrations.
```

O controle do que já foi aplicado fica numa tabela `migration`, criada na primeira execução.

## Criando com os geradores

O `migrate:create` escreve o arquivo da migration para você. O nome da classe começa com `M`, seguido da data e da hora (`M261008045605`, por exemplo), o que mantém as migrations em ordem. A opção `--command` escolhe o gerador:

- `create` (padrão): uma classe com `up()` e `down()` vazios.
- `table`: cria uma tabela, com as colunas de `--fields`.
- `dropTable`: apaga uma tabela.
- `addColumn` e `dropColumn`: acrescentam ou removem colunas.
- `junction`: cria uma tabela de ligação entre duas outras, informadas no argumento e em `--and`.

O `--fields` lista as colunas separadas por vírgula, cada uma no formato `nome:tipo:modificador:modificador`. Tipos e modificadores são os métodos do `ColumnBuilder`, que você vai ver adiante. Para a tabela de autores:

```bash
./yii migrate:create autor --command=table --fields="nome:string(100):notNull"
```

```plaintext
Database connection: sqlite.

	M261008045605CreateAutorTable

 [OK] New migration created successfully.
```

O arquivo gerado, `src/Migration/M261008045605CreateAutorTable.php`, ficou assim, sem nenhuma alteração:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Handles the creation of table `autor`.
 */
final class M261008045605CreateAutorTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('autor', [
            'id' => $columnBuilder::primaryKey(),
            'nome' => $columnBuilder::string(100)->notNull(),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('autor');
    }
}
```

O gerador acrescentou a coluna `id` sozinho. As duas interfaces dizem como a migration se comporta:

- `RevertibleMigrationInterface` exige o `down()`. Uma migration que só implementa `MigrationInterface` tem apenas `up()` e não pode ser revertida.
- `TransactionalMigrationInterface` faz o `up()` e o `down()` rodarem numa transação. Se algo falhar no meio, nada fica pela metade. Atenção: MySQL não desfaz `CREATE TABLE` e outros comandos de estrutura numa transação, então ali a garantia vale só para os dados.

Agora a tabela de livros, com uma chave estrangeira para `autor`. O modificador `foreignKey(tabela)` pede ao gerador a ligação:

```bash
./yii migrate:create livro --command=table --fields="autor_id:integer:notNull:foreignKey(autor),titulo:string(150):notNull,slug:string(100):notNull:unique,ano:integer:notNull,preco:decimal:notNull,cadastrado_em:datetime:notNull"
```

Duas coisas precisaram de ajuste no arquivo gerado.

A primeira é o `preco`. A ideia era `decimal(10,2)`, mas o `--fields` separa as colunas pela vírgula, e a vírgula de dentro dos parênteses quebra o campo em dois. Por isso o comando usa só `decimal`, e o tamanho foi colocado depois, direto no código.

A segunda é a chave estrangeira. O gerador cria a tabela e depois chama `$b->addForeignKey()`. O SQLite não aceita acrescentar chave estrangeira a uma tabela que já existe, e esse método lança `NotSupportedException`. A solução é declarar a referência na própria coluna, com `reference()`, para que ela entre no `CREATE TABLE`. Com MySQL ou PostgreSQL, o arquivo gerado funciona sem mudança.

O resultado, em `src/Migration/M261008045622CreateLivroTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Constant\ReferentialAction;
use Yiisoft\Db\Constraint\ForeignKey;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Handles the creation of table `livro`.
 * Has foreign keys to the tables:
 *
 * - `{{%autor}}`
 */
final class M261008045622CreateLivroTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('livro', [
            'id' => $columnBuilder::primaryKey(),
            'autor_id' => $columnBuilder::integer()->notNull()->reference(new ForeignKey(
                foreignTableName: 'autor',
                foreignColumnNames: ['id'],
                onDelete: ReferentialAction::CASCADE,
            )),
            'titulo' => $columnBuilder::string(150)->notNull(),
            'slug' => $columnBuilder::string(100)->notNull()->unique(),
            'ano' => $columnBuilder::integer()->notNull(),
            'preco' => $columnBuilder::decimal(10, 2)->notNull(),
            'cadastrado_em' => $columnBuilder::datetime()->notNull(),
        ]);

        // creates index for column `autor_id`
        $b->createIndex(
            'livro',
            'idx-livro-autor_id',
            'autor_id'
        );
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('livro');
    }
}
```

O SQL que chegou ao banco:

```plaintext
CREATE TABLE "livro" (
	"id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
	"autor_id" integer NOT NULL REFERENCES "autor" ("id") ON DELETE CASCADE,
	"titulo" varchar(150) NOT NULL,
	"slug" varchar(100) UNIQUE NOT NULL,
	"ano" integer NOT NULL,
	"preco" decimal(10,2) NOT NULL,
	"cadastrado_em" datetime(0) NOT NULL
)
```

O SQLite tem outras limitações do mesmo tipo: `dropColumn()`, `alterColumn()`, `addPrimaryKey()` e `dropForeignKey()` também lançam `NotSupportedException`. Para mudar uma coluna no SQLite, o caminho é criar uma tabela nova com a estrutura certa, copiar os dados e trocar os nomes.

## Migrations de dados

Nem toda migration mexe em estrutura. Para preencher as tabelas com os dados de exemplo, o gerador padrão cria uma classe vazia:

```bash
./yii migrate:create popula_livros
```

O arquivo `src/Migration/M261008045704PopulaLivros.php`, depois de preenchido (os doze livros foram encurtados aqui):

```php
final class M261008045704PopulaLivros implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->insertBatch('autor', [
            [1, 'Machado de Assis'],
            [2, 'Clarice Lispector'],
            [3, 'Jorge Amado'],
            [4, 'Graciliano Ramos'],
        ], ['id', 'nome']);

        $b->insertBatch('livro', [
            [1, 1, 'Memórias Póstumas de Brás Cubas', 'memorias-postumas', 1881, 34.90, '2026-01-05 09:30:00'],
            [2, 1, 'Quincas Borba', 'quincas-borba', 1891, 39.90, '2026-01-12 14:10:00'],
            [3, 1, 'Dom Casmurro', 'dom-casmurro', 1899, 29.90, '2026-02-02 10:00:00'],
            // ...
        ], ['id', 'autor_id', 'titulo', 'slug', 'ano', 'preco', 'cadastrado_em']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->delete('livro');
        $b->delete('autor');
    }
}
```

O gerador `create` não coloca a `TransactionalMigrationInterface`; ela foi acrescentada à mão. Os ids são informados de propósito: assim os livros podem apontar para os autores, e desfazer e refazer a migration recria exatamente os mesmos registros.

O `MigrationBuilder` (`$b`) tem os mesmos métodos de escrita do comando (`insert()`, `insertBatch()`, `update()`, `delete()`, `upsert()`) e os de estrutura (`createTable()`, `addColumn()`, `createIndex()`, `renameColumn()`…). A diferença é que ele executa na hora e imprime cada passo. Se precisar de algo que ele não tem, `$b->getDb()` devolve a conexão, e `$b->execute($sql)` roda SQL direto.

## Aplicando

```bash
./yii migrate:up
```

O comando lista as migrations pendentes, pede confirmação e aplica uma por uma, em ordem:

```plaintext
1. Applying App\Migration\M261008045605CreateAutorTable
========================================================

    > create table autor ... Done in 0.001s.

	[OK] Applied (time: 0.056s)


2. Applying App\Migration\M261008045622CreateLivroTable
========================================================

    > create table livro ... Done in 0.001s.
    > Create index idx-livro-autor_id on livro (autor_id) ... Done in 0.000s.

	[OK] Applied (time: 0.084s)


3. Applying App\Migration\M261008045704PopulaLivros
====================================================

    > Insert into autor ... Inserted 4 rows in 0.003s.
    > Insert into livro ... Inserted 12 rows in 0.005s.

	[OK] Applied (time: 0.012s)

Total 3 new migrations were applied.
```

Antes de aplicar, `./yii migrate:new` mostra o que está pendente. Depois, `./yii migrate:history` mostra o que já foi aplicado, da mais recente para a mais antiga:

```plaintext
Total 4 migrations have been applied before:
--------------------------------------------

	(2026-10-08 01:57:24) App\Migration\M261008045704PopulaLivros
	(2026-10-08 01:57:24) App\Migration\M261008045622CreateLivroTable
	(2026-10-08 01:57:24) App\Migration\M261008045605CreateAutorTable
	(2026-10-08 01:36:58) App\Migration\M261008043647CreatePaisTable
```

Em scripts de deploy, use `-y` (`--force-yes`) para pular a confirmação. Com `--limit=N`, só as N primeiras pendentes são aplicadas.

## O ColumnBuilder

Dentro da migration, `$b->columnBuilder()` devolve a classe `ColumnBuilder` do banco em uso. Os métodos estáticos dela criam a coluna pelo tipo:

- Chaves: `primaryKey()`, `bigPrimaryKey()`, `uuidPrimaryKey()`.
- Números: `integer()`, `smallint()`, `bigint()`, `float()`, `double()`, `decimal($tamanho, $casas)`, `money()`, `boolean()`.
- Texto: `char($tamanho)`, `string($tamanho)` (padrão 255), `text()`.
- Datas: `date()`, `time()`, `datetime()`, `timestamp()`, `datetimeWithTimezone()`.
- Outros: `binary()`, `uuid()`, `json()`, `enum([...])`, `array()`.

Cada um devolve um objeto de coluna, e os modificadores encadeiam:

- `notNull()` e `null()`
- `unique()`
- `defaultValue($valor)`
- `unsigned()`
- `comment('...')`
- `check('preco > 0')`
- `reference(new ForeignKey(...))`

Cada driver converte o tipo para o equivalente do banco. No SQLite, `string(150)` virou `varchar(150)` e `primaryKey()` virou `integer PRIMARY KEY AUTOINCREMENT NOT NULL`. Se precisar de um tipo específico do banco, `dbType('...')` força o nome. Também dá para passar uma string com o tipo em SQL, como `'varchar(20) NOT NULL'`, no lugar do objeto, mas aí a migration deixa de ser portável.

## Revertendo e refazendo

`migrate:down` desfaz a última migration aplicada, chamando o `down()` dela:

```bash
./yii migrate:down
```

```plaintext
Total 1 migration to be reverted:

	1. App\Migration\M261008045704PopulaLivros

 Revert the above migration? (yes/no) [yes]:
 >

1. Reverting App\Migration\M261008045704PopulaLivros
=====================================================

    > Delete from livro ... Deleted 12 rows in 0.001s.
    > Delete from autor ... Deleted 4 rows in 0.000s.

	[OK] Reverted (time: 0.007s)

[OK] 1 migration was reverted.
```

Com `--limit=3`, desfaz as três últimas; com `--all`, todas. Um `./yii migrate:up` em seguida aplica de novo o que foi desfeito.

`migrate:redo` faz as duas coisas de uma vez: desfaz e reaplica. É útil enquanto você ainda está escrevendo a migration e quer testar o `up()` e o `down()`:

```bash
./yii migrate:redo
```

```plaintext
Total 1 migration to be redone:

	1. App\Migration\M261008045704PopulaLivros

1. Reverting App\Migration\M261008045704PopulaLivros
...
1. Applying App\Migration\M261008045704PopulaLivros
...
1 migration was redone.

 [OK] Migration redone successfully.
```

::: warning
Só reverta migrations que ainda não saíram da sua máquina. Depois que uma migration foi aplicada em outro ambiente, o caminho seguro é escrever uma migration nova com a correção. E lembre que `down()` de tabela com dados apaga esses dados.
:::

## Comparando com o Yii2

- As migrations ficam em classes com namespace, configurado nos parâmetros do `yiisoft/db-migration`, em vez da pasta `console/migrations` com nomes `m200101_000000_...`.
- `up()` e `down()` recebem um `MigrationBuilder` em vez de chamar `$this->createTable()`. Não existe mais `safeUp()`: a transação vem da interface `TransactionalMigrationInterface`.
- Os tipos de coluna saem do `ColumnBuilder` (`$columnBuilder::string(100)->notNull()`) em vez de `$this->string(100)->notNull()`.
- `./yii migrate` sem nada virou `./yii migrate:up`. Os geradores com `--fields` continuam, agora escolhidos por `--command`.
