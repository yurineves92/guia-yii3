# Documentação OpenAPI

Quem consome a API precisa saber quais endpoints existem, que parâmetros eles aceitam e o formato de cada resposta. A especificação OpenAPI descreve tudo isso num arquivo JSON ou YAML que ferramentas entendem: o Swagger UI gera uma página navegável, geradores de código criam clientes em várias linguagens e testes conferem se a API cumpre o contrato. Este capítulo gera a especificação da API de tarefas a partir de atributos PHP e a publica com o Swagger UI.

## swagger-php

O pacote `zircote/swagger-php` lê atributos `#[OA\...]` no código e monta a especificação:

```bash
composer require zircote/swagger-php
```

O app de exemplo usa a versão 6. Os atributos ficam no namespace `OpenApi\Attributes`, que costuma ser importado com o apelido `OA`. Cada parte da especificação tem o seu atributo: `OA\Info`, `OA\Get`, `OA\Post`, `OA\Schema`, `OA\Response` e assim por diante, com parâmetros nomeados iguais aos campos do OpenAPI.

Os atributos podem ficar em cima dos próprios métodos do controller, o que mantém a documentação perto do código. Neste guia, eles ficam em classes separadas, em `src/Web/Api/V1/OpenApi/` e `src/Web/Api/V1/Schema/`, para que o controller mostrado em [Recursos e respostas](/api/recursos) continue curto. As duas formas geram o mesmo resultado.

## Os dados gerais

Toda especificação tem um bloco com título, versão e servidores. Crie `src/Web/Api/V1/OpenApi/Especificacao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0.0',
        description: 'API de tarefas do guia Yii3. Erros seguem a RFC 9457 (application/problem+json).',
        title: 'API de tarefas',
    ),
    servers: [new OA\Server(url: '/api/v1', description: 'Este servidor')],
    tags: [new OA\Tag(name: 'Tarefas', description: 'Cadastro de tarefas')],
)]
#[OA\SecurityScheme(
    securityScheme: 'token',
    type: 'http',
    description: 'Token enviado em Authorization: Bearer <token>',
    scheme: 'bearer',
)]
#[OA\Response(
    response: 'ParametrosInvalidos',
    description: 'Parâmetros da requisição fora do permitido',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Response(
    response: 'NaoEncontrado',
    description: 'Recurso não encontrado',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Response(
    response: 'NaoAutenticado',
    description: 'Token ausente, inválido ou expirado',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Response(
    response: 'DadosInvalidos',
    description: 'Os dados enviados não passaram na validação',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Parameter(
    parameter: 'id',
    name: 'id',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', minimum: 1),
)]
final class Especificacao
{
}
```

A classe é vazia: serve só de suporte para os atributos. Além do `OA\OpenApi`, ela declara peças reutilizáveis, que vão para a seção `components` da especificação:

- O `OA\SecurityScheme` chamado `token` descreve a autenticação do capítulo [Autenticação](/api/autenticacao): HTTP com esquema `bearer`.
- Os `OA\Response` com nome são as respostas de erro, todas com o tipo `application/problem+json` do capítulo [Erros](/api/erros). Os endpoints se referem a elas por `#/components/responses/NaoEncontrado`, em vez de repetir a descrição.
- O `OA\Parameter` chamado `id` é o parâmetro de caminho usado por três endpoints.

## Os schemas

Os schemas descrevem o formato dos objetos. Cada um fica numa classe. O da tarefa, em `src/Web/Api/V1/Schema/TarefaSchema.php`, repete o formato do `TarefaResource`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Tarefa',
    required: ['id', 'titulo', 'concluida', 'criadaEm'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'titulo', type: 'string', example: 'Responder os e-mails'),
        new OA\Property(property: 'concluida', type: 'boolean', example: false),
        new OA\Property(property: 'criadaEm', type: 'string', format: 'date-time', example: '2026-10-02T08:15:00-03:00'),
    ],
    type: 'object',
)]
final class TarefaSchema
{
}
```

O corpo aceito em `POST` e `PUT`, em `src/Web/Api/V1/Schema/TarefaInputSchema.php`, traz as mesmas restrições das regras de validação do `TarefaInput`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TarefaInput',
    required: ['titulo'],
    properties: [
        new OA\Property(property: 'titulo', type: 'string', maxLength: 200, minLength: 3, example: 'Ler a RFC 9457'),
        new OA\Property(property: 'concluida', type: 'boolean', default: false),
    ],
    type: 'object',
)]
final class TarefaInputSchema
{
}
```

A lista paginada, em `src/Web/Api/V1/Schema/ListaDeTarefasSchema.php`, aponta para o schema `Tarefa` com `ref`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListaDeTarefas',
    properties: [
        new OA\Property(property: 'dados', type: 'array', items: new OA\Items(ref: '#/components/schemas/Tarefa')),
        new OA\Property(
            property: 'paginacao',
            properties: [
                new OA\Property(property: 'pagina', type: 'integer', example: 1),
                new OA\Property(property: 'porPagina', type: 'integer', example: 5),
                new OA\Property(property: 'totalDeItens', type: 'integer', example: 8),
                new OA\Property(property: 'totalDePaginas', type: 'integer', example: 2),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
final class ListaDeTarefasSchema
{
}
```

E o formato de erro, em `src/Web/Api/V1/Schema/ProblemaSchema.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Problema',
    description: 'Erro no formato da RFC 9457',
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'about:blank'),
        new OA\Property(property: 'title', type: 'string', example: 'Not Found'),
        new OA\Property(property: 'status', type: 'integer', example: 404),
        new OA\Property(property: 'detail', type: 'string', example: 'A tarefa 99 não existe.'),
        new OA\Property(
            property: 'errors',
            description: 'Mensagens de validação por campo (só no 422)',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
    type: 'object',
)]
final class ProblemaSchema
{
}
```

Uma classe por schema facilita achar cada um e evita arquivos enormes.

## Os endpoints

Os endpoints ficam em `src/Web/Api/V1/OpenApi/TarefaEndpoints.php`, com um método vazio por operação:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Documentação dos endpoints de tarefas. Os métodos ficam vazios:
 * o código de verdade está em App\Web\Api\V1\Tarefa\TarefaController.
 */
final class TarefaEndpoints
{
    #[OA\Get(
        path: '/tarefas',
        operationId: 'listarTarefas',
        summary: 'Lista as tarefas, com paginação',
        tags: ['Tarefas'],
        parameters: [
            new OA\Parameter(name: 'pagina', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'porPagina', in: 'query', schema: new OA\Schema(type: 'integer', default: 5, maximum: 50, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Uma página de tarefas', content: new OA\JsonContent(ref: '#/components/schemas/ListaDeTarefas')),
            new OA\Response(response: 400, ref: '#/components/responses/ParametrosInvalidos'),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
        ],
    )]
    public function index(): void {}

    #[OA\Get(
        path: '/tarefas/{id}',
        operationId: 'verTarefa',
        summary: 'Mostra uma tarefa',
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'A tarefa', content: new OA\JsonContent(ref: '#/components/schemas/Tarefa')),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
        ],
    )]
    public function view(): void {}

    #[OA\Post(
        path: '/tarefas',
        operationId: 'criarTarefa',
        summary: 'Cria uma tarefa',
        security: [['token' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TarefaInput')),
        tags: ['Tarefas'],
        responses: [
            new OA\Response(response: 201, description: 'Tarefa criada', content: new OA\JsonContent(ref: '#/components/schemas/Tarefa')),
            new OA\Response(response: 401, ref: '#/components/responses/NaoAutenticado'),
            new OA\Response(response: 422, ref: '#/components/responses/DadosInvalidos'),
        ],
    )]
    public function create(): void {}

    #[OA\Put(
        path: '/tarefas/{id}',
        operationId: 'atualizarTarefa',
        summary: 'Substitui os dados de uma tarefa',
        security: [['token' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TarefaInput')),
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Tarefa atualizada', content: new OA\JsonContent(ref: '#/components/schemas/Tarefa')),
            new OA\Response(response: 401, ref: '#/components/responses/NaoAutenticado'),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
            new OA\Response(response: 422, ref: '#/components/responses/DadosInvalidos'),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/tarefas/{id}',
        operationId: 'apagarTarefa',
        summary: 'Apaga uma tarefa',
        security: [['token' => []]],
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 204, description: 'Tarefa apagada'),
            new OA\Response(response: 401, ref: '#/components/responses/NaoAutenticado'),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
        ],
    )]
    public function delete(): void {}
}
```

- O `path` é relativo ao servidor declarado em `Especificacao` (`/api/v1`).
- O `operationId` dá um nome estável a cada operação. Geradores de cliente usam esse nome para os métodos. Sem ele, o swagger-php gera um hash.
- O `security: [['token' => []]]` marca as operações que exigem o token. As de leitura não têm, porque são públicas.
- O `OA\JsonContent` é um atalho para conteúdo `application/json` com um schema.

O lado ruim de documentar em classes separadas é que nada obriga a documentação a acompanhar o código. Se você mudar uma rota ou um campo e esquecer de mudar aqui, a especificação mente. Com os atributos em cima dos métodos do controller, a diferença fica mais visível numa revisão, mas o risco é o mesmo. Um teste que compara as respostas reais com os schemas resolve isso de vez.

## Servindo a especificação

A especificação é gerada a cada requisição por uma action, em `src/Web/Api/Documentacao/EspecificacaoAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Documentacao;

use OpenApi\Generator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Header;

/**
 * Gera a especificação OpenAPI a partir dos atributos #[OA\...] da versão 1.
 */
final readonly class EspecificacaoAction
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Aliases $aliases,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $openApi = (new Generator($this->logger))
            ->setVersion('3.1.0')
            ->generate([
                $this->aliases->get('@src/Web/Api/V1/OpenApi'),
                $this->aliases->get('@src/Web/Api/V1/Schema'),
            ]);

        $response = $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'application/json; charset=utf-8');
        $response->getBody()->write($openApi->toJson());

        return $response;
    }
}
```

O `Generator` varre as pastas indicadas, lê os atributos e devolve um objeto `OpenApi`, que vira JSON com `toJson()` (ou YAML, com `toYaml()`). O logger recebe os avisos do swagger-php, como uma referência a um schema que não existe; eles vão para o log da aplicação. O `setVersion('3.1.0')` escolhe a versão do formato OpenAPI, que não tem nada a ver com a versão da sua API.

A rota fica no grupo `/v1`, porque a especificação descreve essa versão:

```php
Route::get('/openapi.json')
    ->action(Api\Documentacao\EspecificacaoAction::class)
    ->name('openapi'),
```

```bash
curl http://127.0.0.1:8080/api/v1/openapi.json
```

```json
{
    "openapi": "3.1.0",
    "info": {
        "title": "API de tarefas",
        "description": "API de tarefas do guia Yii3. Erros seguem a RFC 9457 (application/problem+json).",
        "version": "1.0.0"
    },
    "servers": [
        {
            "url": "/api/v1",
            "description": "Este servidor"
        }
    ],
    "paths": {
        "/tarefas": {
            "get": {
                "tags": [
                    "Tarefas"
                ],
                "summary": "Lista as tarefas, com paginação",
                "operationId": "listarTarefas",
```

Varrer arquivos a cada requisição custa algum tempo. Em produção, gere o arquivo uma vez, no deploy, com o comando que vem no pacote, e sirva o arquivo estático:

```bash
vendor/bin/openapi --version 3.1.0 -o public/openapi.yaml src/Web/Api/V1/OpenApi src/Web/Api/V1/Schema
```

Outra saída é guardar o JSON gerado no [cache de dados](/cache/cache-de-dados).

## Swagger UI

O Swagger UI é uma página JavaScript que lê a especificação e mostra cada endpoint, com um botão para testá-lo. Ele não precisa de instalação no PHP: basta uma página que carrega os arquivos de uma CDN. Crie `src/Web/Api/Documentacao/SwaggerUiAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Documentacao;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Router\UrlGeneratorInterface;

/**
 * Página com o Swagger UI, carregado de uma CDN, apontando para a especificação.
 */
final readonly class SwaggerUiAction
{
    private const VERSAO_SWAGGER_UI = '5.17.14';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $cdn = 'https://unpkg.com/swagger-ui-dist@' . self::VERSAO_SWAGGER_UI;
        $especificacao = json_encode($this->urlGenerator->generate('api/v1/openapi'), JSON_UNESCAPED_SLASHES);

        $html = <<<HTML
            <!doctype html>
            <html lang="pt-BR">
            <head>
                <meta charset="utf-8">
                <title>API de tarefas</title>
                <link rel="stylesheet" href="$cdn/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="$cdn/swagger-ui-bundle.js"></script>
                <script>
                    SwaggerUIBundle({ url: $especificacao, dom_id: '#swagger-ui' });
                </script>
            </body>
            </html>
            HTML;

        $response = $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'text/html; charset=utf-8');
        $response->getBody()->write($html);

        return $response;
    }
}
```

- A versão do Swagger UI fica fixa numa constante. Uma URL sem versão pegaria sempre a mais nova, e uma atualização da biblioteca poderia mudar a página sem você saber.
- A URL da especificação vem do `UrlGeneratorInterface`, pelo nome da rota, e o `json_encode()` a transforma numa string JavaScript válida.
- Como a página é uma só e não usa o layout do site, o HTML está na própria action. Se ela crescer, vale passar para uma view.

A rota fica no grupo `/api`:

```php
Route::get('/docs')
    ->action(Api\Documentacao\SwaggerUiAction::class)
    ->name('docs'),
```

Abra http://127.0.0.1:8080/api/docs. A página mostra o título "API de tarefas", o servidor `/api/v1`, a tag "Tarefas" com as cinco operações (as três de escrita com um cadeado) e, no fim, os schemas. Abrindo `GET /tarefas` e clicando em "Try it out" e depois em "Execute", o Swagger UI faz a requisição `/api/v1/tarefas?pagina=1&porPagina=5` e mostra a resposta `200`.

Para testar as operações protegidas, clique em "Authorize" e cole o token, sem o prefixo `Bearer`: o Swagger UI monta o cabeçalho a partir do `SecurityScheme`.

A página de documentação não precisa ser pública. Se a API é interna, coloque a rota `/docs` e a da especificação num grupo com autenticação, ou sirva-as só em desenvolvimento.

## Comparando com o Yii2

O Yii2 não tinha geração de documentação de API. Era comum usar o próprio swagger-php, nas versões antigas, com anotações em comentários (`@OA\Get(...)`) dentro de docblocks. O swagger-php atual aceita atributos nativos do PHP 8, que são verificados pela linguagem e entendidos pelo editor, e é o que o Yii3 usa sem nenhuma integração especial: o pacote é independente do framework.
