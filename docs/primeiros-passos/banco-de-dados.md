# Trabalhando com banco de dados

Neste capítulo, vamos criar uma tabela de países, preencher com alguns registros e mostrar tudo numa página com paginação. Para não precisar instalar nada, o banco vai ser SQLite, que guarda tudo num arquivo.

## Instalando os pacotes

```bash
composer require yiisoft/db-sqlite yiisoft/db-migration yiisoft/cache yiisoft/data yiisoft/data-db
```

- `yiisoft/db-sqlite`: a conexão com SQLite e o query builder
- `yiisoft/db-migration`: os comandos `./yii migrate:*`
- `yiisoft/cache`: a conexão guarda a estrutura das tabelas em cache
- `yiisoft/data` e `yiisoft/data-db`: paginação em cima de uma consulta

Confira se o PHP tem a extensão `pdo_sqlite` com `php -m`.

## Configurando a conexão

No Yii3, configurar um serviço é dizer ao container como montá-lo. Crie `config/common/di/db.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Aliases\Aliases;
use Yiisoft\Cache\ArrayCache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

return [
    SchemaCache::class => [
        '__construct()' => [new ArrayCache()],
    ],

    ConnectionInterface::class => static fn (Aliases $aliases, SchemaCache $schemaCache) => new Connection(
        new Driver('sqlite:' . $aliases->get('@runtime/database.sqlite')),
        $schemaCache,
    ),
];
```

Todo arquivo dentro de `config/common/di/` é carregado automaticamente, então não precisa registrar este em lugar nenhum. A partir de agora, qualquer classe que pedir `ConnectionInterface` no construtor recebe esta conexão. O banco fica em `runtime/database.sqlite`.

Para usar MySQL ou PostgreSQL, você troca o pacote (`yiisoft/db-mysql`, `yiisoft/db-pgsql`) e este arquivo. O resto do código continua igual, porque ele depende da interface, e não do banco.

## Configurando as migrations

As migrations precisam saber em qual namespace ficam. Em `config/console/params.php`, adicione a chave `yiisoft/db-migration`:

```php
<?php

declare(strict_types=1);

return [
    'yiisoft/yii-console' => [
        'commands' => require __DIR__ . '/commands.php',
    ],

    'yiisoft/db-migration' => [
        'newMigrationNamespace' => 'App\Migration',
        'sourceNamespaces' => ['App\Migration'],
    ],
];
```

## Criando a tabela

Crie a pasta e peça para o gerador escrever a migration:

```bash
mkdir -p src/Migration
./yii migrate:create pais --command=table --fields="codigo:char(2):notNull:unique,nome:string(52):notNull,populacao:integer:notNull"
```

Ele cria um arquivo como `src/Migration/M261008043647CreatePaisTable.php`, com o número correspondente à data e hora. Abra e adicione, logo depois do `createTable()`, os registros de exemplo:

```php
final class M261008043647CreatePaisTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('pais', [
            'id' => $columnBuilder::primaryKey(),
            'codigo' => $columnBuilder::char(2)->notNull()->unique(),
            'nome' => $columnBuilder::string(52)->notNull(),
            'populacao' => $columnBuilder::integer()->notNull(),
        ]);

        $b->insertBatch('pais', [
            ['AU', 'Austrália', 26_000_000],
            ['BR', 'Brasil', 212_000_000],
            ['CA', 'Canadá', 40_000_000],
            ['CN', 'China', 1_410_000_000],
            ['DE', 'Alemanha', 83_000_000],
            ['FR', 'França', 68_000_000],
            ['GB', 'Reino Unido', 68_000_000],
            ['IN', 'Índia', 1_440_000_000],
            ['PT', 'Portugal', 10_600_000],
            ['US', 'Estados Unidos', 335_000_000],
        ], ['codigo', 'nome', 'populacao']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('pais');
    }
}
```

O `up()` aplica a mudança e o `down()` desfaz. Como a classe implementa `TransactionalMigrationInterface`, tudo roda numa transação: se o insert falhar, a tabela também não é criada.

Aplique:

```bash
./yii migrate:up
```

```plaintext
    > create table pais ... Done in 0.001s.
	[OK] Applied (time: 0.081s)
```

Para desfazer a última migration, use `./yii migrate:down`.

## A página

Crie `src/Web/Pais/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Pais;

use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
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

    public function __invoke(ServerRequestInterface $request, #[Query('pagina')] int $pagina = 1): ResponseInterface
    {
        $query = $this->db->select()->from('pais')->orderBy(['nome' => SORT_ASC]);

        $paginator = (new OffsetPaginator(new QueryDataReader($query)))->withPageSize(5);

        if ($pagina < 1 || $pagina > $paginator->getTotalPages()) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'paginator' => $paginator->withCurrentPage($pagina),
        ]);
    }
}
```

Vamos por partes:

- `$this->db->select()->from('pais')->orderBy(...)` monta a consulta com o query builder. Ela ainda não foi executada.
- O `QueryDataReader` embrulha a consulta, e o `OffsetPaginator` cuida do `LIMIT`, do `OFFSET` e da contagem. Com `withPageSize(5)`, são 5 países por página.
- Uma página que não existe (`?pagina=99`) ou um valor que não é número devolve 404, com a página de erro do template.
- O `withCurrentPage()` devolve um novo paginador, sem alterar o original. Esse padrão `with*()` aparece em todo o Yii3.

Agora a view, em `src/Web/Pais/template.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Paginator\OffsetPaginator $paginator
 */

$this->setTitle('Países');
?>

<h1>Países</h1>

<ul>
    <?php foreach ($paginator->read() as $pais): ?>
        <li>
            <?= Html::encode($pais['nome']) ?> (<?= Html::encode($pais['codigo']) ?>):
            <?= number_format((int) $pais['populacao'], 0, ',', '.') ?>
        </li>
    <?php endforeach ?>
</ul>

<nav>
    <?php for ($pagina = 1; $pagina <= $paginator->getTotalPages(); $pagina++): ?>
        <?php if ($pagina === $paginator->getCurrentPage()): ?>
            <strong><?= $pagina ?></strong>
        <?php else: ?>
            <a href="<?= $urlGenerator->generate('pais', queryParameters: ['pagina' => $pagina]) ?>"><?= $pagina ?></a>
        <?php endif ?>
    <?php endfor ?>
</nav>
```

O `read()` executa a consulta e devolve só os registros da página atual. Cada registro é um array com as colunas da tabela.

## A rota

```php
Route::get('/paises')
    ->action(Web\Pais\Action::class)
    ->name('pais'),
```

## Testando

Abra http://127.0.0.1:8080/paises. Aparecem os 5 primeiros países em ordem alfabética, de Alemanha a China, e os links para as páginas 1 e 2. A página 2 (`/paises?pagina=2`) mostra os outros 5, e `/paises?pagina=99` dá 404.

## Comparando com o Yii2

No Yii2, este exemplo usava um `ActiveRecord` chamado `Country` e o `Pagination`. Aqui, usamos o query builder direto, sem classe de modelo, e o paginador do `yiisoft/data`. Se você prefere trabalhar com Active Record, ele existe no Yii3 como o pacote opcional `yiisoft/active-record`, e o capítulo [Active Record](/banco-de-dados/active-record) vai cobrir isso.
