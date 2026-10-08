# Domínio: entidades e serviços

O Yii3 não diz como modelar o seu negócio. Não existe uma classe `Model` para estender, e o template não traz pasta de models. Isso é proposital: a regra de negócio é código PHP comum, e o framework fica na borda, recebendo a requisição e entregando a resposta. Este capítulo monta um exemplo pequeno, uma lista de tarefas, para mostrar onde cada parte fica e como manter o domínio independente do HTTP.

## Onde fica a regra de negócio

A regra do exemplo é simples: uma tarefa pode ser concluída, mas não duas vezes. A pergunta é onde colocar esse `if`.

Na action seria o caminho mais curto, mas aí a regra só vale para quem passa por aquela action. Um comando de console ou uma API que concluísse tarefas teria que repetir a verificação. O lugar certo é a própria tarefa.

O código de domínio desta seção fica em `src/Estrutura/`, fora de `src/Web/`. A entidade, em `src/Estrutura/Tarefa/Tarefa.php`:

```php
<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DateTimeImmutable;

final class Tarefa
{
    public function __construct(
        private readonly string $titulo,
        private ?int $id = null,
        private ?DateTimeImmutable $concluidaEm = null,
    ) {}

    public function concluir(DateTimeImmutable $quando): void
    {
        if ($this->concluidaEm !== null) {
            throw new TarefaJaConcluidaException($this);
        }

        $this->concluidaEm = $quando;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function definirId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function getConcluidaEm(): ?DateTimeImmutable
    {
        return $this->concluidaEm;
    }

    public function estaConcluida(): bool
    {
        return $this->concluidaEm !== null;
    }
}
```

A classe não estende nada e não importa nada do Yii. A data de conclusão é recebida como argumento, em vez de a entidade chamar `new DateTimeImmutable()`; assim um teste pode passar a data que quiser. O `id` é nulo enquanto a tarefa não foi salva.

A quebra da regra vira uma exceção de domínio, em `src/Estrutura/Tarefa/TarefaJaConcluidaException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DomainException;

final class TarefaJaConcluidaException extends DomainException
{
    public function __construct(Tarefa $tarefa)
    {
        parent::__construct(sprintf('A tarefa "%s" já foi concluída.', $tarefa->getTitulo()));
    }
}
```

E há uma segunda, `TarefaNaoEncontradaException`, que também estende `DomainException`, recebe o id no construtor e monta a mensagem `Tarefa %d não encontrada.`.

## Repositories e serviços

### O repositório

A entidade não sabe se salvar. Quem busca e grava tarefas é um repositório. Primeiro a interface, em `src/Estrutura/Tarefa/TarefaRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

interface TarefaRepositoryInterface
{
    public function buscar(int $id): ?Tarefa;

    /**
     * @return Tarefa[]
     */
    public function todas(): array;

    public function salvar(Tarefa $tarefa): void;
}
```

A tabela é criada por uma migration, em `src/Migration/M261008050500CreateEstruturaTarefaTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cria a tabela `estrutura_tarefa`, usada no capítulo "Domínio: entidades e serviços".
 */
final class M261008050500CreateEstruturaTarefaTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('estrutura_tarefa', [
            'id' => $columnBuilder::primaryKey(),
            'titulo' => $columnBuilder::string(100)->notNull(),
            'concluida_em' => $columnBuilder::datetime()->null(),
        ]);

        $b->insertBatch('estrutura_tarefa', [
            ['Escrever o capítulo de domínio'],
            ['Revisar os exemplos'],
            ['Publicar o guia'],
        ], ['titulo']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('estrutura_tarefa');
    }
}
```

Aplique com `./yii migrate:up`. Migrations têm capítulo próprio em [Migrations](/banco-de-dados/migrations).

A implementação do repositório usa o query builder, em `src/Estrutura/Tarefa/DbTarefaRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DateTimeImmutable;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class DbTarefaRepository implements TarefaRepositoryInterface
{
    private const TABELA = 'estrutura_tarefa';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function buscar(int $id): ?Tarefa
    {
        $linha = $this->db->select()->from(self::TABELA)->where(['id' => $id])->one();

        return $linha === null ? null : $this->criar($linha);
    }

    public function todas(): array
    {
        $linhas = $this->db->select()->from(self::TABELA)->orderBy(['id' => SORT_ASC])->all();

        return array_map($this->criar(...), $linhas);
    }

    public function salvar(Tarefa $tarefa): void
    {
        $colunas = [
            'titulo' => $tarefa->getTitulo(),
            'concluida_em' => $tarefa->getConcluidaEm()?->format('Y-m-d H:i:s'),
        ];

        $id = $tarefa->getId();

        if ($id === null) {
            $chaves = $this->db->createCommand()->insertReturningPks(self::TABELA, $colunas);
            $tarefa->definirId((int) $chaves['id']);

            return;
        }

        $this->db->createCommand()->update(self::TABELA, $colunas, ['id' => $id])->execute();
    }

    private function criar(array $linha): Tarefa
    {
        return new Tarefa(
            titulo: (string) $linha['titulo'],
            id: (int) $linha['id'],
            concluidaEm: $linha['concluida_em'] === null ? null : new DateTimeImmutable((string) $linha['concluida_em']),
        );
    }
}
```

Toda a conversão entre linha da tabela e objeto fica aqui. Se um dia as tarefas forem para outro banco, ou para uma API, só esta classe muda. O query builder está em [Query builder](/banco-de-dados/query-builder).

Quem pede `TarefaRepositoryInterface` precisa receber a implementação. Isso é uma linha no container, em `config/common/di/estrutura.php`:

```php
<?php

declare(strict_types=1);

use App\Estrutura\LojaParams;
use App\Estrutura\Tarefa\DbTarefaRepository;
use App\Estrutura\Tarefa\TarefaRepositoryInterface;

/** @var array $params */

return [
    LojaParams::class => [
        '__construct()' => [
            'nome' => $params['estrutura']['loja']['nome'],
            'email' => $params['estrutura']['loja']['email'],
        ],
    ],

    TarefaRepositoryInterface::class => DbTarefaRepository::class,
];
```

A definição de `LojaParams` é do capítulo [Aplicação](/estrutura/aplicacao). A definição fica em `config/common/` porque um comando de console também poderia usar o repositório.

### O serviço

Concluir uma tarefa envolve três passos: buscar, concluir e salvar. Esse fluxo é um caso de uso, e vai num serviço, em `src/Estrutura/Tarefa/ConcluirTarefa.php`:

```php
<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DateTimeImmutable;

final readonly class ConcluirTarefa
{
    public function __construct(
        private TarefaRepositoryInterface $tarefas,
    ) {}

    /**
     * @throws TarefaNaoEncontradaException
     * @throws TarefaJaConcluidaException
     */
    public function executar(int $id): Tarefa
    {
        $tarefa = $this->tarefas->buscar($id) ?? throw new TarefaNaoEncontradaException($id);

        $tarefa->concluir(new DateTimeImmutable());
        $this->tarefas->salvar($tarefa);

        return $tarefa;
    }
}
```

O serviço depende da interface, não da classe com banco. Ele não precisa de definição no container: o autowiring resolve o construtor, e a interface já está ligada.

Nem todo caso de uso precisa de serviço. Criar uma tarefa é só `new Tarefa($titulo)` seguido de `salvar()`; a action de criação, mais abaixo, faz isso direto com o repositório. Crie um serviço quando o fluxo tem passos, regras ou mais de um repositório, ou quando ele é chamado de mais de um lugar.

## Mantendo o domínio longe do HTTP

As actions ficam em `src/Web/Estrutura/Tarefa/`. O papel delas é traduzir: transformar a requisição em chamadas ao domínio e o resultado em resposta.

A action de concluir, em `src/Web/Estrutura/Tarefa/ConcluirAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Tarefa;

use App\Estrutura\Tarefa\ConcluirTarefa;
use App\Estrutura\Tarefa\TarefaJaConcluidaException;
use App\Estrutura\Tarefa\TarefaNaoEncontradaException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class ConcluirAction
{
    public function __construct(
        private ConcluirTarefa $concluirTarefa,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(#[RouteArgument('id')] int $id): ResponseInterface
    {
        try {
            $this->concluirTarefa->executar($id);
        } catch (TarefaNaoEncontradaException $e) {
            return $this->erro(Status::NOT_FOUND, $e->getMessage());
        } catch (TarefaJaConcluidaException $e) {
            return $this->erro(Status::CONFLICT, $e->getMessage());
        }

        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('estrutura/tarefas'));
    }

    private function erro(int $status, string $mensagem): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($mensagem . "\n");

        return $response;
    }
}
```

O domínio só sabe que a tarefa não existe ou já foi concluída. Que isso vira 404 e 409 é uma decisão da camada web. Uma API responderia com JSON; um comando de console imprimiria a mensagem e sairia com código de erro. O serviço é o mesmo nos três casos.

A action de criar, em `src/Web/Estrutura/Tarefa/CriarAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Tarefa;

use App\Estrutura\Tarefa\Tarefa;
use App\Estrutura\Tarefa\TarefaRepositoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class CriarAction
{
    public function __construct(
        private TarefaRepositoryInterface $tarefas,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(#[Body('titulo')] string $titulo = ''): ResponseInterface
    {
        $titulo = trim($titulo);

        if ($titulo === '') {
            $response = $this->responseFactory->createResponse(Status::UNPROCESSABLE_ENTITY);
            $response->getBody()->write("Informe o título da tarefa.\n");

            return $response;
        }

        $this->tarefas->salvar(new Tarefa($titulo));

        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('estrutura/tarefas'));
    }
}
```

A verificação do título vazio é validação de entrada, e fica na web. Com mais campos, o caminho é um formulário com regras de validação, visto em [Validação](/formularios/validacao).

A listagem, em `src/Web/Estrutura/Tarefa/ListarAction.php`, só passa as tarefas para a view:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Tarefa;

use App\Estrutura\Tarefa\TarefaRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class ListarAction
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private TarefaRepositoryInterface $tarefas,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/listar', [
            'tarefas' => $this->tarefas->todas(),
        ]);
    }
}
```

E a view, em `src/Web/Estrutura/Tarefa/listar.php`, mostra um botão "Concluir" para cada tarefa pendente e um campo para criar outra:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Estrutura\Tarefa\Tarefa[] $tarefas
 */

$this->setTitle('Tarefas');
?>

<h1>Tarefas</h1>

<ul>
    <?php foreach ($tarefas as $tarefa): ?>
        <li>
            <?= Html::encode($tarefa->getTitulo()) ?>
            <?php if ($tarefa->estaConcluida()): ?>
                (concluída em <?= $tarefa->getConcluidaEm()->format('d/m/Y H:i') ?>)
            <?php else: ?>
                <?= Html::form()
                    ->post($urlGenerator->generate('estrutura/tarefa/concluir', ['id' => $tarefa->getId()]))
                    ->csrf($csrf)
                    ->open() ?>
                    <?= Html::submitButton('Concluir') ?>
                <?= Html::form()->close() ?>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ul>

<?= Html::form()->post($urlGenerator->generate('estrutura/tarefa/criar'))->csrf($csrf)->open() ?>
    <?= Html::textInput('titulo')->addAttributes(['placeholder' => 'Nova tarefa']) ?>
    <?= Html::submitButton('Adicionar') ?>
<?= Html::form()->close() ?>
```

As rotas:

```php
Route::get('/estrutura/tarefas')
    ->action(Estrutura\Tarefa\ListarAction::class)
    ->name('estrutura/tarefas'),
Route::post('/estrutura/tarefas')
    ->action(Estrutura\Tarefa\CriarAction::class)
    ->name('estrutura/tarefa/criar'),
Route::post('/estrutura/tarefas/{id:\d+}/concluir')
    ->action(Estrutura\Tarefa\ConcluirAction::class)
    ->name('estrutura/tarefa/concluir'),
```

O `{id:\d+}` só aceita dígitos, então `/estrutura/tarefas/abc/concluir` nem chega na action: o `Router` não encontra rota e a resposta é o 404 do fallback handler.

### Testando

Em `/estrutura/tarefas` aparecem as três tarefas da migration, cada uma com o botão "Concluir".

- Clicando em "Concluir" na segunda, a página volta para a lista (resposta `302` com `Location: /estrutura/tarefas`) e a tarefa aparece como "Revisar os exemplos (concluída em 08/10/2026 02:00)", com a data e a hora do momento.
- Reenviando o mesmo POST, a resposta é `409 Conflict` com o texto `A tarefa "Revisar os exemplos" já foi concluída.`.
- Um POST para `/estrutura/tarefas/99/concluir` devolve `404 Not Found` com `Tarefa 99 não encontrada.`.
- Enviando o formulário de criação com o título em branco, a resposta é `422` com `Informe o título da tarefa.`; com um título, a nova tarefa entra no fim da lista.

### O que essa separação permite

Olhe os `use` das classes de `src/Estrutura/Tarefa/`: nenhuma importa `Psr\Http`, `WebViewRenderer` ou qualquer coisa de rota. Só o `DbTarefaRepository` conhece o banco. Isso tem efeitos práticos:

- A `Tarefa` e o `ConcluirTarefa` podem ser testados com PHPUnit puro, usando um repositório falso em memória que implementa a interface. Veja [Testes unitários](/testes/unitarios).
- Um comando de console pode receber `ConcluirTarefa` no construtor e reaproveitar a regra. Veja [Comandos de console](/topicos/console).
- Trocar o armazenamento é trocar uma linha no container.

## Comparando com o Yii2

- No Yii2, o padrão era um `ActiveRecord` que fazia tudo: regras de validação, acesso ao banco, regras de negócio e, às vezes, até formatação para a view. No Yii3, essas responsabilidades ficam em classes separadas.
- O `yiisoft/active-record` existe para quem prefere esse estilo, e pode ser usado dentro de um repositório. Veja [Active Record](/banco-de-dados/active-record).
- O Yii2 não tinha container usado em todo lugar, então injetar um repositório por interface exigia configurar o `Yii::$container` à mão. No Yii3, é o caminho padrão.
