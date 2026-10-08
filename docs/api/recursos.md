# Recursos e respostas

Numa API REST, cada coisa que o cliente manipula é um recurso com uma URL própria, e os métodos HTTP dizem o que fazer com ele: `GET` lê, `POST` cria, `PUT` substitui, `DELETE` apaga. Este capítulo monta o recurso "tarefa" completo e trata de duas questões que aparecem em toda API: em que formato os dados saem e como paginar listas longas.

## A tabela

Crie a migration `src/Migration/M261008051000CreateApiTarefaTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cria a tabela `api_tarefa`, usada pelos exemplos de API REST.
 */
final class M261008051000CreateApiTarefaTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('api_tarefa', [
            'id' => $columnBuilder::primaryKey(),
            'titulo' => $columnBuilder::string(200)->notNull(),
            'concluida' => $columnBuilder::boolean()->notNull()->defaultValue(false),
            'criada_em' => $columnBuilder::datetime()->notNull(),
        ]);

        $b->insertBatch('api_tarefa', [
            ['Comprar café', true, '2026-10-01 09:00:00'],
            ['Revisar o capítulo de cache', true, '2026-10-01 10:30:00'],
            ['Responder os e-mails', false, '2026-10-02 08:15:00'],
            ['Atualizar as dependências', false, '2026-10-02 14:00:00'],
            ['Escrever os testes da API', false, '2026-10-03 11:45:00'],
            ['Marcar a reunião de planejamento', true, '2026-10-04 16:20:00'],
            ['Trocar a senha do banco de homologação', false, '2026-10-05 09:10:00'],
            ['Documentar os endpoints', false, '2026-10-06 13:00:00'],
        ], ['titulo', 'concluida', 'criada_em']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('api_tarefa');
    }
}
```

E aplique com `./yii migrate:up`.

## O repositório

O acesso ao banco fica fora da camada web, em `src/Api/Tarefa/TarefaRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Api\Tarefa;

use DateTimeImmutable;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class TarefaRepository
{
    private const TABELA = 'api_tarefa';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function leitor(): QueryDataReader
    {
        return new QueryDataReader(
            $this->db->select()->from(self::TABELA)->orderBy(['id' => SORT_ASC]),
        );
    }

    public function buscar(int $id): ?array
    {
        return $this->db->select()->from(self::TABELA)->where(['id' => $id])->one();
    }

    public function criar(string $titulo, bool $concluida): int
    {
        $this->db->createCommand()->insert(self::TABELA, [
            'titulo' => $titulo,
            'concluida' => $concluida,
            'criada_em' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ])->execute();

        return (int) $this->db->getLastInsertId();
    }

    /**
     * @param array{titulo?: string, concluida?: bool} $campos
     */
    public function atualizar(int $id, array $campos): void
    {
        $this->db->createCommand()->update(self::TABELA, $campos, ['id' => $id])->execute();
    }

    public function apagar(int $id): bool
    {
        return $this->db->createCommand()->delete(self::TABELA, ['id' => $id])->execute() > 0;
    }
}
```

O `leitor()` devolve um `QueryDataReader`, o mesmo usado na paginação de [Trabalhando com banco de dados](/primeiros-passos/banco-de-dados). A consulta só roda quando alguém lê os dados.

## Formatando a saída

Devolver a linha do banco direto no JSON parece prático, mas amarra a API ao banco. O SQLite devolve tudo como texto (`"id": "3"`, `"concluida": "0"`), os nomes das colunas viram parte do contrato com os clientes, e qualquer coluna nova aparece na resposta sem ninguém decidir isso.

Por isso, a conversão fica numa classe à parte, em `src/Web/Api/V1/Tarefa/TarefaResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Tarefa;

use DateTimeImmutable;

/**
 * Converte uma linha da tabela `api_tarefa` no formato da versão 1 da API.
 */
final class TarefaResource
{
    /**
     * @return array{id: int, titulo: string, concluida: bool, criadaEm: string}
     */
    public static function deLinha(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'concluida' => (bool) $linha['concluida'],
            'criadaEm' => (new DateTimeImmutable($linha['criada_em']))->format(DATE_ATOM),
        ];
    }
}
```

Ela decide os tipos (número, booleano), os nomes dos campos (`criadaEm` em vez de `criada_em`) e o formato da data (ISO 8601, com fuso). É o único lugar que conhece o formato da tarefa na versão 1. O capítulo [Versionamento](/api/versionamento) mostra por que isso importa.

## Os dados de entrada

Para criar e atualizar, a API recebe um JSON com `titulo` e `concluida`. Os dados chegam numa classe de entrada, em `src/Web/Api/V1/Tarefa/TarefaInput.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Tarefa;

use Yiisoft\Input\Http\AbstractInput;
use Yiisoft\Input\Http\Attribute\Data\FromBody;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

#[FromBody]
final class TarefaInput extends AbstractInput
{
    #[Required(message: 'Informe o título.')]
    #[Length(
        min: 3,
        max: 200,
        lessThanMinMessage: 'O título deve ter pelo menos {min} caracteres.',
        greaterThanMaxMessage: 'O título deve ter no máximo {max} caracteres.',
        skipOnEmpty: true,
    )]
    public string $titulo = '';

    public bool $concluida = false;
}
```

Quando uma action recebe um parâmetro do tipo `TarefaInput`, o Yii3 cria o objeto, preenche as propriedades com o corpo da requisição (é o que o `#[FromBody]` diz) e roda as regras de validação. O resultado fica em `getValidationResult()`. Isso funciona porque a classe estende `AbstractInput` e o template já registra o `RequestInputParametersResolver`, que faz esse trabalho. As propriedades têm tipo, então `"concluida": 1` vira `true` e campos desconhecidos são ignorados.

## O controller

Como as cinco operações compartilham as mesmas dependências, elas ficam num controller com um método por operação, em `src/Web/Api/V1/Tarefa/TarefaController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Tarefa;

use App\Api\Tarefa\TarefaRepository;
use App\Web\Api\Shared\Problemas;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class TarefaController
{
    private const POR_PAGINA_MAXIMO = 50;

    public function __construct(
        private TarefaRepository $tarefas,
        private JsonResponseFactory $json,
        private ResponseFactoryInterface $responseFactory,
        private Problemas $problemas,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(
        #[Query('pagina')] int $pagina = 1,
        #[Query('porPagina')] int $porPagina = 5,
    ): ResponseInterface {
        if ($pagina < 1 || $porPagina < 1 || $porPagina > self::POR_PAGINA_MAXIMO) {
            return $this->problemas->criar(
                Status::BAD_REQUEST,
                'Use pagina >= 1 e porPagina entre 1 e ' . self::POR_PAGINA_MAXIMO . '.',
            );
        }

        $paginator = (new OffsetPaginator($this->tarefas->leitor()))->withPageSize($porPagina);

        if ($pagina > max(1, $paginator->getTotalPages())) {
            return $this->problemas->naoEncontrado("A página $pagina não existe.");
        }

        $paginator = $paginator->withCurrentPage($pagina);

        $dados = [];
        foreach ($paginator->read() as $linha) {
            $dados[] = TarefaResource::deLinha($linha);
        }

        return $this->json->createResponse([
            'dados' => $dados,
            'paginacao' => [
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'totalDeItens' => $paginator->getTotalItems(),
                'totalDePaginas' => $paginator->getTotalPages(),
            ],
        ]);
    }

    public function view(#[RouteArgument('id')] int $id): ResponseInterface
    {
        $linha = $this->tarefas->buscar($id);

        if ($linha === null) {
            return $this->problemas->naoEncontrado("A tarefa $id não existe.");
        }

        return $this->json->createResponse(TarefaResource::deLinha($linha));
    }

    public function create(TarefaInput $input): ResponseInterface
    {
        $resultado = $input->getValidationResult();
        if (!$resultado->isValid()) {
            return $this->problemas->validacao($resultado);
        }

        $id = $this->tarefas->criar($input->titulo, $input->concluida);

        return $this->json
            ->createResponse(TarefaResource::deLinha($this->tarefas->buscar($id)), Status::CREATED)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('api/v1/tarefas/view', ['id' => $id]));
    }

    public function update(#[RouteArgument('id')] int $id, TarefaInput $input): ResponseInterface
    {
        if ($this->tarefas->buscar($id) === null) {
            return $this->problemas->naoEncontrado("A tarefa $id não existe.");
        }

        $resultado = $input->getValidationResult();
        if (!$resultado->isValid()) {
            return $this->problemas->validacao($resultado);
        }

        $this->tarefas->atualizar($id, ['titulo' => $input->titulo, 'concluida' => $input->concluida]);

        return $this->json->createResponse(TarefaResource::deLinha($this->tarefas->buscar($id)));
    }

    public function delete(#[RouteArgument('id')] int $id): ResponseInterface
    {
        if (!$this->tarefas->apagar($id)) {
            return $this->problemas->naoEncontrado("A tarefa $id não existe.");
        }

        return $this->responseFactory->createResponse(Status::NO_CONTENT);
    }
}
```

O status de cada resposta faz parte do contrato:

- `index()` e `view()` respondem `200`. Uma tarefa que não existe dá `404`.
- `create()` responde `201 Created`, com a tarefa criada no corpo e a URL dela no cabeçalho `Location`. O cliente não precisa adivinhar onde o recurso novo ficou.
- `update()` responde `200` com a tarefa já atualizada, para o cliente não precisar fazer outro `GET`.
- `delete()` responde `204 No Content`, sem corpo.

Os parâmetros com `#[Query]` vêm da query string, e os com `#[RouteArgument]` vêm da URL. A classe `Problemas` monta as respostas de erro no formato Problem Details; ela é o assunto do capítulo [Erros](/api/erros).

Este `update()` implementa o `PUT`, que substitui o recurso inteiro: um campo que não vier no JSON volta ao valor padrão da classe de entrada. Uma atualização parcial, com `PATCH`, precisaria distinguir "não enviado" de "enviado vazio", o que pede uma classe de entrada com propriedades anuláveis.

## As rotas

No arquivo de rotas, dentro do grupo `/api`, as rotas da versão 1 ficam num grupo próprio:

```php
Group::create('/v1')
    ->namePrefix('v1/')
    ->routes(
        Route::get('/tarefas')
            ->action([Api\V1\Tarefa\TarefaController::class, 'index'])
            ->name('tarefas/index'),
        Route::get('/tarefas/{id:\d+}')
            ->action([Api\V1\Tarefa\TarefaController::class, 'view'])
            ->name('tarefas/view'),
        Route::post('/tarefas')
            ->action([Api\V1\Tarefa\TarefaController::class, 'create'])
            ->name('tarefas/create'),
        Route::put('/tarefas/{id:\d+}')
            ->action([Api\V1\Tarefa\TarefaController::class, 'update'])
            ->name('tarefas/update'),
        Route::delete('/tarefas/{id:\d+}')
            ->action([Api\V1\Tarefa\TarefaController::class, 'delete'])
            ->name('tarefas/delete'),
    ),
```

A action de um controller é indicada como array: `[classe, método]`. Os prefixos de nome se somam, então a rota de uma tarefa se chama `api/v1/tarefas/view`, que é o nome usado no `Location`.

No capítulo [Autenticação](/api/autenticacao), as três rotas de escrita passam para um grupo que exige token.

## Testando

A lista, com os valores padrão:

```bash
curl http://127.0.0.1:8080/api/v1/tarefas
```

```json
{"dados":[{"id":1,"titulo":"Comprar café","concluida":true,"criadaEm":"2026-10-01T09:00:00-03:00"},{"id":2,"titulo":"Revisar o capítulo de cache","concluida":true,"criadaEm":"2026-10-01T10:30:00-03:00"},{"id":3,"titulo":"Responder os e-mails","concluida":false,"criadaEm":"2026-10-02T08:15:00-03:00"},{"id":4,"titulo":"Atualizar as dependências","concluida":false,"criadaEm":"2026-10-02T14:00:00-03:00"},{"id":5,"titulo":"Escrever os testes da API","concluida":false,"criadaEm":"2026-10-03T11:45:00-03:00"}],"paginacao":{"pagina":1,"porPagina":5,"totalDeItens":8,"totalDePaginas":2}}
```

A segunda página, com três tarefas por página (formatado para leitura):

```bash
curl "http://127.0.0.1:8080/api/v1/tarefas?pagina=2&porPagina=3"
```

```json
{
    "dados": [
        {
            "id": 4,
            "titulo": "Atualizar as dependências",
            "concluida": false,
            "criadaEm": "2026-10-02T14:00:00-03:00"
        },
        {
            "id": 5,
            "titulo": "Escrever os testes da API",
            "concluida": false,
            "criadaEm": "2026-10-03T11:45:00-03:00"
        },
        {
            "id": 6,
            "titulo": "Marcar a reunião de planejamento",
            "concluida": true,
            "criadaEm": "2026-10-04T16:20:00-03:00"
        }
    ],
    "paginacao": {
        "pagina": 2,
        "porPagina": 3,
        "totalDeItens": 8,
        "totalDePaginas": 3
    }
}
```

Uma tarefa, e uma que não existe:

```bash
curl http://127.0.0.1:8080/api/v1/tarefas/3
curl -i http://127.0.0.1:8080/api/v1/tarefas/99
```

```plaintext
{"id":3,"titulo":"Responder os e-mails","concluida":false,"criadaEm":"2026-10-02T08:15:00-03:00"}

HTTP/1.1 404 Not Found
Content-Type: application/problem+json; charset=UTF-8
Content-Length: 91

{"type":"about:blank","title":"Not Found","status":404,"detail":"A tarefa 99 não existe."}
```

Criando, atualizando e apagando:

```bash
curl -i -X POST -H "Content-Type: application/json" -d '{"titulo":"Ler a RFC 9457"}' http://127.0.0.1:8080/api/v1/tarefas
```

```plaintext
HTTP/1.1 201 Created
Content-Type: application/json; charset=UTF-8
Location: /api/v1/tarefas/9
Content-Length: 91

{"id":9,"titulo":"Ler a RFC 9457","concluida":false,"criadaEm":"2026-10-08T02:08:06-03:00"}
```

```bash
curl -X PUT -H "Content-Type: application/json" -d '{"titulo":"Ler a RFC 9457 inteira","concluida":true}' http://127.0.0.1:8080/api/v1/tarefas/9
```

```json
{"id":9,"titulo":"Ler a RFC 9457 inteira","concluida":true,"criadaEm":"2026-10-08T02:08:06-03:00"}
```

```bash
curl -i -X DELETE http://127.0.0.1:8080/api/v1/tarefas/9
```

```plaintext
HTTP/1.1 204 No Content
```

Repetindo o `DELETE`, a resposta é `404`, porque a tarefa já não existe.

## Paginação em API

O `index()` usa o `OffsetPaginator`, que pula as primeiras `(pagina - 1) * porPagina` linhas. Algumas decisões que valem para qualquer listagem de API:

- **Limite o tamanho da página.** Sem o máximo de 50, um cliente poderia pedir `porPagina=1000000` e derrubar o servidor. Valores fora da faixa respondem `400`.
- **Ordene sempre.** Sem `ORDER BY`, o banco pode devolver as linhas em qualquer ordem, e a mesma tarefa pode aparecer em duas páginas. O `leitor()` ordena por `id`.
- **Diga ao cliente onde ele está.** Aqui, os metadados vão no corpo, no objeto `paginacao`. Outra convenção comum é mandá-los em cabeçalhos, como `Link` (com as URLs da próxima página e da anterior) e `X-Total-Count`. Escolha uma e use em toda a API.

A paginação por deslocamento tem dois pontos fracos: o `OFFSET` fica lento em tabelas grandes, e itens inseridos durante a navegação deslocam as páginas seguintes. Para feeds e tabelas grandes, a alternativa é a paginação por chave, em que o cliente pede "os próximos depois do id 42". O `yiisoft/data` tem o `KeysetPaginator` para isso, tratado no capítulo [Paginação](/dados/paginacao).

## Comparando com o Yii2

No Yii2, o `yii\rest\ActiveController` gerava as cinco ações a partir de um `ActiveRecord`, e o formato da saída vinha dos métodos `fields()` e `extraFields()` do modelo, aplicados pelo `yii\rest\Serializer`. A paginação ia em cabeçalhos `X-Pagination-*`. No Yii3 não há controller REST pronto: você escreve as actions, e o formato da saída fica numa classe sua, como o `TarefaResource`, separada do acesso ao banco.
