# Versionamento

Depois que um cliente começa a usar a API, o formato das respostas vira um contrato. Renomear um campo ou trocar o tipo dele quebra o app de quem depende disso, e você não controla quando esses clientes vão se atualizar. Versionar a API permite mudar o formato sem quebrar ninguém: os clientes antigos continuam na versão antiga, e os novos usam a nova. Este capítulo cria a versão 2 do recurso de tarefas ao lado da versão 1.

## O que quebra um cliente

Nem toda mudança pede uma versão nova. Em geral:

- **Não quebra:** acrescentar um campo novo na resposta, um endpoint novo, um parâmetro opcional novo.
- **Quebra:** remover ou renomear um campo, mudar o tipo de um campo (de booleano para texto, por exemplo), tornar obrigatório um parâmetro que era opcional, mudar o significado de um status HTTP.

Por isso, clientes bem escritos ignoram campos que não conhecem. E por isso também vale a pena pensar bem no formato da primeira versão.

## Onde colocar a versão

Há três formas comuns de o cliente dizer qual versão quer:

- **Na URL:** `/api/v1/tarefas`, `/api/v2/tarefas`. É a mais visível: dá para testar no navegador, aparece nos logs, e cada versão tem suas rotas.
- **Num cabeçalho:** um tipo de mídia próprio no `Accept` (`application/vnd.exemplo.v2+json`) ou um cabeçalho como `Api-Version: 2`. A URL do recurso não muda, mas a versão fica escondida, e o roteamento passa a depender de um middleware que leia o cabeçalho.
- **Na query string:** `/api/tarefas?versao=2`. Simples, mas mistura a versão com os filtros e é fácil de esquecer.

Este guia usa a versão na URL. Ela se encaixa direto nos grupos de rota do Yii3, sem código extra.

## Grupos de rota por versão

As rotas da API já estão organizadas assim desde o capítulo [Recursos e respostas](/api/recursos): o grupo `/api` contém o grupo `/v1`. A versão 2 é só mais um grupo ao lado:

```php
Group::create('/api')
    ->namePrefix('api/')
    ->routes(
        // ... /ping, /eco, /docs
        Group::create('/v1')
            ->namePrefix('v1/')
            ->routes(
                // ... as rotas da versão 1
            ),
        Group::create('/v2')
            ->namePrefix('v2/')
            ->routes(
                Route::get('/tarefas')
                    ->action([Api\V2\Tarefa\TarefaController::class, 'index'])
                    ->name('tarefas/index'),
                Route::get('/tarefas/{id:\d+}')
                    ->action([Api\V2\Tarefa\TarefaController::class, 'view'])
                    ->name('tarefas/view'),
            ),
    ),
```

Cada versão tem o seu prefixo de nome, então `api/v1/tarefas/view` e `api/v2/tarefas/view` são rotas diferentes e o `UrlGeneratorInterface` gera a URL certa para cada uma. Middlewares que valem para todas as versões, como o leitor do corpo JSON, ficam no grupo `/api`. Os que são de uma versão só ficam no grupo dela.

## O código de cada versão

No código, a separação acompanha as rotas: a versão 1 está em `src/Web/Api/V1/` e a versão 2 em `src/Web/Api/V2/`. Só a camada web é duplicada. O `TarefaRepository`, em `src/Api/Tarefa/`, é o mesmo para as duas: a regra de negócio e o banco não sabem que existem versões.

A mudança da versão 2 é no formato da tarefa: o booleano `concluida` vira o campo `status`, com os valores `pendente` ou `concluida`, o que deixa espaço para outros estados no futuro. Crie `src/Web/Api/V2/Tarefa/TarefaResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V2\Tarefa;

use DateTimeImmutable;

/**
 * Formato da versão 2: `concluida` (booleano) virou `status` (texto).
 */
final class TarefaResource
{
    /**
     * @return array{id: int, titulo: string, status: string, criadaEm: string}
     */
    public static function deLinha(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'status' => $linha['concluida'] ? 'concluida' : 'pendente',
            'criadaEm' => (new DateTimeImmutable($linha['criada_em']))->format(DATE_ATOM),
        ];
    }
}
```

E o controller da versão 2, em `src/Web/Api/V2/Tarefa/TarefaController.php`, que também simplifica a paginação: o tamanho da página é fixo e os metadados ficam só com a página atual e o total de páginas:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V2\Tarefa;

use App\Api\Tarefa\TarefaRepository;
use App\Web\Api\Shared\Problemas;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class TarefaController
{
    private const POR_PAGINA = 5;

    public function __construct(
        private TarefaRepository $tarefas,
        private JsonResponseFactory $json,
        private Problemas $problemas,
    ) {}

    public function index(#[Query('pagina')] int $pagina = 1): ResponseInterface
    {
        $paginator = (new OffsetPaginator($this->tarefas->leitor()))->withPageSize(self::POR_PAGINA);

        if ($pagina < 1 || $pagina > max(1, $paginator->getTotalPages())) {
            return $this->problemas->naoEncontrado("A página $pagina não existe.");
        }

        $dados = [];
        foreach ($paginator->withCurrentPage($pagina)->read() as $linha) {
            $dados[] = TarefaResource::deLinha($linha);
        }

        return $this->json->createResponse([
            'dados' => $dados,
            'paginacao' => [
                'pagina' => $pagina,
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
}
```

Para manter o exemplo curto, a versão 2 tem só as rotas de leitura. Numa API real, ela teria todas as operações, mesmo as que não mudaram, para que o cliente use uma versão só. Quando a lógica de uma operação é igual nas duas versões, o controller da v2 pode chamar os mesmos serviços que o da v1. O que não deve acontecer é a v2 reaproveitar o `TarefaResource` da v1, ou o contrário: assim, uma correção numa versão não muda a outra sem querer.

## Testando

A mesma tarefa nas duas versões:

```bash
curl http://127.0.0.1:8080/api/v1/tarefas/3
curl http://127.0.0.1:8080/api/v2/tarefas/3
```

```plaintext
{"id":3,"titulo":"Responder os e-mails","concluida":false,"criadaEm":"2026-10-02T08:15:00-03:00"}
{"id":3,"titulo":"Responder os e-mails","status":"pendente","criadaEm":"2026-10-02T08:15:00-03:00"}
```

E a segunda página da lista na versão 2:

```bash
curl "http://127.0.0.1:8080/api/v2/tarefas?pagina=2"
```

```json
{"dados":[{"id":6,"titulo":"Marcar a reunião de planejamento","status":"concluida","criadaEm":"2026-10-04T16:20:00-03:00"},{"id":7,"titulo":"Trocar a senha do banco de homologação","status":"pendente","criadaEm":"2026-10-05T09:10:00-03:00"},{"id":8,"titulo":"Documentar os endpoints","status":"pendente","criadaEm":"2026-10-06T13:00:00-03:00"}],"paginacao":{"pagina":2,"totalDePaginas":2}}
```

Os clientes da versão 1 não perceberam nada.

## Aposentando uma versão

Manter versões tem custo: cada correção precisa ser feita em mais de um lugar. Por isso, as versões antigas devem ter data para acabar. Um caminho comum:

1. Anuncie a data na documentação e avise os clientes conhecidos.
2. Acrescente às respostas da versão antiga o cabeçalho `Sunset` (RFC 8594), com a data em que ela deixa de funcionar, e o `Deprecation`, que indica que ela está obsoleta. Um middleware no grupo `/v1` resolve isso num lugar só.
3. Acompanhe nos logs quem ainda usa a versão antiga.
4. Na data, remova o grupo de rotas e a pasta da versão.

## Comparando com o Yii2

No Yii2, a recomendação do guia era um módulo por versão (`modules/v1`, `modules/v2`), cada um com seus controllers, e as regras de URL apontando para o módulo certo. No Yii3 não há módulos: a versão é um grupo de rotas, e o código de cada versão fica num namespace próprio, como `App\Web\Api\V1` e `App\Web\Api\V2`.
