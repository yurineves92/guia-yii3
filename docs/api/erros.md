# Erros

Um cliente de API não lê páginas de erro: ele precisa de um status HTTP correto e de um corpo que o programa consiga interpretar. Se cada endpoint inventar o seu formato, o cliente acaba com um `if` para cada caso. Este capítulo adota o formato padrão Problem Details para todos os erros da API: os previstos (recurso inexistente, dados inválidos, JSON malformado) e os imprevistos (uma exceção no meio do código).

## Problem Details (RFC 9457)

A RFC 9457 define um formato JSON para erros de APIs HTTP, com o tipo de mídia `application/problem+json`. Ela substitui a RFC 7807, com o mesmo formato. Os campos são:

- `type`: uma URI que identifica o tipo do problema. O valor `about:blank` diz que o problema é só o que o status HTTP já indica.
- `title`: um resumo curto do tipo do problema. Com `about:blank`, é o texto do status, como `Not Found`.
- `status`: o código HTTP, repetido no corpo.
- `detail`: uma explicação desta ocorrência, para pessoas.
- `instance`: opcional, uma URI desta ocorrência.

Qualquer outro campo é permitido como extensão. Aqui usamos `errors` para as mensagens de validação.

## A classe Problemas

Todas as respostas de erro da API saem de uma classe só, em `src/Web/Api/Shared/Problemas.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\Formatter\JsonFormatter;
use Yiisoft\DataResponse\ResponseFactory\FormattedResponseFactory;
use Yiisoft\Http\Status;
use Yiisoft\Validator\Result;

/**
 * Respostas de erro no formato Problem Details (RFC 9457).
 */
final readonly class Problemas
{
    private FormattedResponseFactory $factory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->factory = new FormattedResponseFactory(
            $responseFactory,
            new JsonFormatter(contentType: 'application/problem+json'),
        );
    }

    /**
     * @param array<string, mixed> $extras Campos adicionais do problema.
     */
    public function criar(int $status, string $detalhe, array $extras = []): ResponseInterface
    {
        return $this->factory->createResponse(
            [
                'type' => 'about:blank',
                'title' => Status::TEXTS[$status] ?? 'Error',
                'status' => $status,
                'detail' => $detalhe,
                ...$extras,
            ],
            $status,
        );
    }

    public function naoEncontrado(string $detalhe = 'Recurso não encontrado.'): ResponseInterface
    {
        return $this->criar(Status::NOT_FOUND, $detalhe);
    }

    public function validacao(Result $resultado): ResponseInterface
    {
        return $this->criar(
            Status::UNPROCESSABLE_ENTITY,
            'Os dados enviados são inválidos.',
            ['errors' => $resultado->getErrorMessagesIndexedByPath()],
        );
    }
}
```

O `JsonResponseFactory` dos capítulos anteriores sempre usa `application/json`. Para trocar o tipo de mídia, montamos um `FormattedResponseFactory` com um `JsonFormatter` configurado com `application/problem+json`. O título vem de `Status::TEXTS`, a tabela de textos de status do `yiisoft/http`.

O `TarefaController` do capítulo [Recursos e respostas](/api/recursos) já usa essa classe:

```bash
curl -i http://127.0.0.1:8080/api/v1/tarefas/99
```

```plaintext
HTTP/1.1 404 Not Found
Content-Type: application/problem+json; charset=UTF-8
Content-Length: 91

{"type":"about:blank","title":"Not Found","status":404,"detail":"A tarefa 99 não existe."}
```

## Erros de validação

As regras da classe `TarefaInput` produzem um `Result` do `yiisoft/validator`. O `getErrorMessagesIndexedByPath()` devolve as mensagens agrupadas pelo nome do campo, cada uma numa lista, porque um campo pode ter mais de um erro. O status é `422 Unprocessable Content`: o JSON está correto, mas o conteúdo não passa nas regras.

Um título curto demais:

```bash
curl -i -X POST -H "Content-Type: application/json" \
  -H "Authorization: Bearer token-de-exemplo-nao-use-em-producao" \
  -d '{"titulo":"x"}' http://127.0.0.1:8080/api/v1/tarefas
```

```plaintext
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/problem+json; charset=UTF-8
Content-Length: 180

{"type":"about:blank","title":"Unprocessable entity","status":422,"detail":"Os dados enviados são inválidos.","errors":{"titulo":["O título deve ter pelo menos 3 caracteres."]}}
```

Sem o título:

```json
{"type":"about:blank","title":"Unprocessable entity","status":422,"detail":"Os dados enviados são inválidos.","errors":{"titulo":["Informe o título."]}}
```

O cliente pode mostrar cada mensagem ao lado do campo correspondente, sem interpretar o texto. Para dados aninhados, o caminho usa ponto: `endereco.cep`.

## Corpo inválido

Se o JSON estiver malformado, o `RequestBodyParser` responde antes da action. O padrão dele é texto puro:

```plaintext
HTTP/1.1 400 Bad Request
Content-Length: 59
Content-type: text/html; charset=UTF-8

Bad Request
Invalid JSON data in request body: Syntax error
```

O construtor do `RequestBodyParser` aceita um handler para esse caso, que implementa `BadRequestHandlerInterface`. Crie `src/Web/Api/Shared/CorpoInvalidoHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\BadRequestHandlerInterface;
use Yiisoft\Request\Body\ParserException;

/**
 * Resposta do RequestBodyParser quando o corpo da requisição não pode ser lido.
 */
final class CorpoInvalidoHandler implements BadRequestHandlerInterface
{
    private ?ParserException $erro = null;

    public function __construct(
        private readonly Problemas $problemas,
    ) {}

    public function withParserException(ParserException $e): BadRequestHandlerInterface
    {
        $new = clone $this;
        $new->erro = $e;
        return $new;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemas->criar(
            Status::BAD_REQUEST,
            $this->erro?->getMessage() ?? 'O corpo da requisição é inválido.',
        );
    }
}
```

O parser chama `withParserException()` com o erro de leitura antes de chamar `handle()`. A classe não é `readonly` porque esse método precisa alterar a cópia.

## Erros inesperados

Falta o caso mais delicado: uma exceção que ninguém tratou, como uma conexão que cai. Para ver o que acontece, crie uma action que falha de propósito, em `src/Web/Api/Erro/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Erro;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Lança uma exceção de propósito, para mostrar como a API responde a um erro inesperado.
 */
final readonly class Action
{
    public function __invoke(): ResponseInterface
    {
        throw new RuntimeException('Falha simulada: a conexão com o serviço de estoque caiu.');
    }
}
```

Com a rota `Route::get('/erro')` no grupo `/api`, a exceção chega ao `ErrorCatcher` da pilha global. Ele escolhe o formato pelo cabeçalho `Accept` da requisição. Sem `Accept`, a resposta é a página HTML de erro, com mais de 200 KB no modo de depuração. Com `Accept: application/json`, sai um JSON, que em modo de depuração traz a classe da exceção, a mensagem, o arquivo e o rastreamento completo (trecho, com o caminho do arquivo encurtado):

```plaintext
HTTP/1.1 500 Internal Server Error
Content-Type: application/json
Content-Length: 10394

{
    "type": "RuntimeException",
    "message": "Falha simulada: a conex\u00e3o com o servi\u00e7o de estoque caiu.",
    "code": 0,
    "file": "C:\\Users\\...\\app\\src\\Web\\Api\\Erro\\Action.php",
    "line": 17,
    "trace": [
```

Nenhum dos dois é Problem Details, e o segundo mostra caminhos de arquivo. Fora do modo de depuração, o `ErrorCatcher` esconde os detalhes e devolve só `{"message":"An internal server error occurred."}`, mas o formato continua diferente do resto da API.

A saída é um middleware no grupo `/api` que captura qualquer exceção, registra no log e responde com um problema genérico. Crie `src/Web/Api/Shared/ErroInternoMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Yiisoft\Http\Status;

/**
 * Transforma qualquer exceção não tratada numa resposta 500 em problem+json.
 *
 * Os detalhes vão para o log, nunca para o cliente.
 */
final readonly class ErroInternoMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Problemas $problemas,
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return $this->problemas->criar(
                Status::INTERNAL_SERVER_ERROR,
                'Ocorreu um erro inesperado. Tente de novo mais tarde.',
            );
        }
    }
}
```

O preço é que, em desenvolvimento, você não vê mais a página de depuração para erros da API: o rastreamento fica no log (`runtime/logs/app.log`). Se preferir, registre o middleware só fora do ambiente de desenvolvimento.

Quando algumas exceções precisam virar respostas específicas (uma `EstoqueIndisponivel` que deve virar `409`, por exemplo), o `yiisoft/error-handler` tem o middleware `ExceptionResponder`, que recebe um mapa de classe de exceção para status ou para uma função que monta a resposta.

## Ligando tudo no grupo

O `CorpoInvalidoHandler` e o `ErroInternoMiddleware` entram na lista de middlewares do grupo `/api`, no arquivo de rotas:

```php
use Yiisoft\Definitions\Reference;
use Yiisoft\Request\Body\RequestBodyParser;

Group::create('/api')
    ->namePrefix('api/')
    ->middleware(
        Api\Shared\ErroInternoMiddleware::class,
        [
            'class' => RequestBodyParser::class,
            '__construct()' => [
                'badRequestHandler' => Reference::to(Api\Shared\CorpoInvalidoHandler::class),
            ],
        ],
    )
```

- A ordem importa: o primeiro middleware da lista envolve os outros. O `ErroInternoMiddleware` vem antes para capturar exceções de tudo o que vem depois, inclusive do parser.
- O `RequestBodyParser` agora é uma definição em array, no mesmo formato das definições do container: a classe e os argumentos do construtor. O `Reference::to()` pede ao container uma instância do `CorpoInvalidoHandler`.

O resultado:

```bash
curl -i -X POST -H "Content-Type: application/json" \
  -H "Authorization: Bearer token-de-exemplo-nao-use-em-producao" \
  -d '{"titulo": ' http://127.0.0.1:8080/api/v1/tarefas
```

```plaintext
HTTP/1.1 400 Bad Request
Content-Type: application/problem+json; charset=UTF-8
Content-Length: 116

{"type":"about:blank","title":"Bad Request","status":400,"detail":"Invalid JSON data in request body: Syntax error"}
```

```bash
curl -i http://127.0.0.1:8080/api/erro
```

```plaintext
HTTP/1.1 500 Internal Server Error
Content-Type: application/problem+json; charset=UTF-8
Content-Length: 132

{"type":"about:blank","title":"Internal Server Error","status":500,"detail":"Ocorreu um erro inesperado. Tente de novo mais tarde."}
```

E no log:

```plaintext
2026-10-08 05:11:32.374700 [error][application] Falha simulada: a conexão com o serviço de estoque caiu.
```

## Rotas que não existem

Um caminho sem rota, como `/api/v1/naoexiste`, não chega a nenhum middleware do grupo: o roteador passa a requisição para o handler de "não encontrado" da aplicação, que no template devolve a página HTML de 404. Para a API responder em JSON, a última rota do grupo `/api` aceita qualquer caminho:

```php
Route::methods([Method::GET, Method::POST, Method::PUT, Method::PATCH, Method::DELETE], '/{caminho:.+}')
    ->action(Api\Shared\NaoEncontradoAction::class),
```

A action fica em `src/Web/Api/Shared/NaoEncontradoAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Responde com 404 em problem+json a qualquer caminho de /api que não tenha rota.
 */
final readonly class NaoEncontradoAction
{
    public function __construct(
        private Problemas $problemas,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemas->naoEncontrado(
            'Nenhum endpoint em ' . $request->getUri()->getPath() . '.',
        );
    }
}
```

```bash
curl -i http://127.0.0.1:8080/api/v1/naoexiste
```

```plaintext
HTTP/1.1 404 Not Found
Content-Type: application/problem+json; charset=UTF-8
Content-Length: 104

{"type":"about:blank","title":"Not Found","status":404,"detail":"Nenhum endpoint em /api/v1/naoexiste."}
```

Essa rota tem um efeito colateral. Sem ela, um método que a rota não aceita, como `PATCH /api/v1/tarefas/1`, recebe do roteador um `405 Method Not Allowed` com o cabeçalho `Allow`. Com ela, o `PATCH` casa com a rota genérica e a resposta passa a ser `404`. O `OPTIONS` ficou fora da lista de métodos de propósito: assim o roteador continua respondendo `204` com o `Allow` às requisições de verificação dos navegadores. Se o `405` for importante para os seus clientes, deixe a rota genérica de fora e aceite o 404 em HTML para caminhos inexistentes.

## Comparando com o Yii2

No Yii2, o `ErrorHandler` convertia exceções em JSON quando a resposta era JSON, com campos como `name`, `message`, `code` e `status`, e o `yii\rest\Serializer` devolvia os erros de validação como `422` com uma lista de pares `field` e `message`. No Yii3, não existe um formato de erro pronto para APIs: você escolhe o formato, e o Problem Details é a escolha padronizada. As peças para aplicá-lo são as mesmas do resto da aplicação: uma classe que monta a resposta, um middleware para as exceções e o handler de erro do `RequestBodyParser`.
