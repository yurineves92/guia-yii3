# Autenticação

Uma API pública de leitura pode atender qualquer um, mas criar, alterar e apagar dados exige saber quem está pedindo. Numa API, a forma mais comum de fazer isso é um token enviado em cada requisição, no cabeçalho `Authorization: Bearer <token>`. Este capítulo guarda tokens no banco, usa o middleware de autenticação do `yiisoft/auth` para conferi-los e protege as rotas de escrita das tarefas.

Para login com sessão e senha, em páginas do site, veja o capítulo [Autenticação](/seguranca/autenticacao) da seção de segurança.

## O pacote yiisoft/auth

```bash
composer require yiisoft/auth
```

O pacote separa o problema em três partes:

- O middleware `Yiisoft\Auth\Middleware\Authentication`, que roda antes da action, tenta identificar o cliente e responde `401` se não conseguir.
- O método de autenticação, que diz onde procurar a credencial. O `HttpBearer` lê o cabeçalho `Authorization: Bearer`. Há também `HttpHeader` (um cabeçalho próprio, por padrão `X-Api-Key`), `QueryParameter` (na query string, por padrão `access-token`), `HttpBasic`, `HttpCookie` e `Composite`, que tenta vários em sequência.
- Um repositório de identidades, que você escreve. Para tokens, ele implementa `IdentityWithTokenRepositoryInterface`, com um único método: `findIdentityByToken(string $token, ?string $type = null)`. Ele devolve um objeto que implementa `IdentityInterface`, ou `null` se o token não vale.

## A tabela de tokens

Crie a migration `src/Migration/M261008051100CreateApiTokenTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Migration;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Cria a tabela `api_token`, com dois tokens de exemplo.
 *
 * O banco guarda só o hash SHA-256 de cada token.
 */
final class M261008051100CreateApiTokenTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $columnBuilder = $b->columnBuilder();

        $b->createTable('api_token', [
            'id' => $columnBuilder::primaryKey(),
            'cliente' => $columnBuilder::string(100)->notNull(),
            'token_hash' => $columnBuilder::char(64)->notNull()->unique(),
            'expira_em' => $columnBuilder::datetime()->null(),
        ]);

        $b->insertBatch('api_token', [
            ['Cliente de exemplo', hash('sha256', 'token-de-exemplo-nao-use-em-producao'), null],
            ['Cliente antigo', hash('sha256', 'token-de-exemplo-expirado'), '2026-01-01 00:00:00'],
        ], ['cliente', 'token_hash', 'expira_em']);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('api_token');
    }
}
```

O banco não guarda o token, só o hash SHA-256 dele. Se alguém ler a tabela (um backup vazado, uma consulta indevida), não consegue usar os tokens. A ideia é a mesma de guardar o hash de uma senha, mas aqui um hash rápido como o SHA-256 basta: o token é longo e aleatório, então não dá para descobri-lo por tentativa. Isso também permite buscar pelo hash com um `WHERE` simples, o que não seria possível com o `password_hash()`.

Os dois tokens de exemplo servem para os testes: um vale para sempre, o outro já expirou. Os valores estão no código de propósito; num sistema real, nenhum token fica escrito no repositório.

## A identidade e o repositório

A identidade é o cliente dono do token. Crie `src/Api/Token/ClienteDaApi.php`:

```php
<?php

declare(strict_types=1);

namespace App\Api\Token;

use Yiisoft\Auth\IdentityInterface;

/**
 * O cliente identificado pelo token enviado na requisição.
 */
final readonly class ClienteDaApi implements IdentityInterface
{
    public function __construct(
        public int $id,
        public string $nome,
    ) {}

    public function getId(): string
    {
        return (string) $this->id;
    }
}
```

E o repositório, em `src/Api/Token/TokenRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Api\Token;

use DateTimeImmutable;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Security\Random;

final readonly class TokenRepository implements IdentityWithTokenRepositoryInterface
{
    private const TABELA = 'api_token';

    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function findIdentityByToken(string $token, ?string $type = null): ?ClienteDaApi
    {
        $linha = $this->db->select(['id', 'cliente'])
            ->from(self::TABELA)
            ->where(['token_hash' => hash('sha256', $token)])
            ->andWhere([
                'or',
                ['expira_em' => null],
                ['>', 'expira_em', (new DateTimeImmutable())->format('Y-m-d H:i:s')],
            ])
            ->one();

        return $linha === null ? null : new ClienteDaApi((int) $linha['id'], $linha['cliente']);
    }

    /**
     * Cria um token para o cliente e devolve o valor em texto puro.
     *
     * O valor não fica salvo em lugar nenhum: mostre-o uma única vez a quem pediu.
     */
    public function emitir(string $cliente, ?DateTimeImmutable $expiraEm = null): string
    {
        $token = Random::string(40);

        $this->db->createCommand()->insert(self::TABELA, [
            'cliente' => $cliente,
            'token_hash' => hash('sha256', $token),
            'expira_em' => $expiraEm?->format('Y-m-d H:i:s'),
        ])->execute();

        return $token;
    }
}
```

O `findIdentityByToken()` calcula o hash do token recebido e procura uma linha com esse hash que não tenha expirado. O parâmetro `$type` serve para quem tem mais de um tipo de token (de acesso, de atualização) na mesma tabela; aqui não é usado.

O `emitir()` gera 40 caracteres aleatórios com o `Random` do `yiisoft/security`, grava o hash e devolve o token. É assim que um token novo seria criado, numa tela do painel ou num comando de console:

```php
$token = $tokenRepository->emitir('App do celular', new DateTimeImmutable('+30 days'));
```

O valor devolvido aparece uma vez para quem pediu e não pode ser recuperado depois. Para revogar um token, basta apagar a linha.

## A resposta de falha

Sem configuração, o `Authentication` responde `401` com o texto "Your request was made with invalid credentials.". Para manter o padrão de erros da API, passamos um handler próprio, em `src/Web/Api/Shared/FalhaDeAutenticacao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;

/**
 * Resposta do middleware de autenticação quando não há token válido.
 */
final readonly class FalhaDeAutenticacao implements RequestHandlerInterface
{
    public function __construct(
        private Problemas $problemas,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemas->criar(
            Status::UNAUTHORIZED,
            'Envie um token válido no cabeçalho Authorization: Bearer <token>.',
        );
    }
}
```

O `Problemas` é a classe de respostas de erro do capítulo [Erros](/api/erros).

## Protegendo as rotas

No arquivo de rotas, as rotas de escrita das tarefas vão para um grupo sem prefixo dentro de `/v1`, com o middleware de autenticação:

```php
use App\Api\Token\TokenRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\Auth\Method\HttpBearer;
use Yiisoft\Auth\Middleware\Authentication;

Group::create('/v1')
    ->namePrefix('v1/')
    ->routes(
        // ... openapi.json e as duas rotas de leitura
        Group::create()
            ->middleware(
                static fn (
                    TokenRepository $tokens,
                    ResponseFactoryInterface $responseFactory,
                    Api\Shared\FalhaDeAutenticacao $falha,
                ) => new Authentication(new HttpBearer($tokens), $responseFactory, $falha),
            )
            ->routes(
                Route::get('/eu')
                    ->action(Api\V1\Eu\Action::class)
                    ->name('eu'),
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
    ),
```

Por que uma função, e não só `Authentication::class`? O construtor do `Authentication` recebe o método de autenticação tipado como uma união de interfaces, e o container não tem como adivinhar qual implementação usar. O roteador aceita como middleware uma função que devolve um middleware: os parâmetros da função são resolvidos pelo container, e o middleware devolvido é executado em seguida. Outra saída seria registrar o `Authentication` no container, com uma definição em `config/web/di/`; a função na rota deixa a configuração perto das rotas que ela protege.

As rotas de leitura continuam públicas. Se a API inteira exigir token, o middleware vai direto no grupo `/v1`.

## Quem está chamando

Quando o token vale, o `Authentication` guarda a identidade num atributo da requisição, com o nome da própria classe. A rota `/eu` mostra isso. Crie `src/Web/Api/V1/Eu/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Eu;

use App\Api\Token\ClienteDaApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ClienteDaApi $cliente */
        $cliente = $request->getAttribute(Authentication::class);

        return $this->responseFactory->createResponse([
            'id' => $cliente->id,
            'cliente' => $cliente->nome,
        ]);
    }
}
```

Qualquer action protegida pode ler o cliente do mesmo jeito, por exemplo para registrar quem criou cada tarefa.

## Testando

Sem token:

```bash
curl -i http://127.0.0.1:8080/api/v1/eu
```

```plaintext
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json; charset=UTF-8
WWW-Authenticate: Bearer realm="api"
Content-Length: 137

{"type":"about:blank","title":"Unauthorized","status":401,"detail":"Envie um token válido no cabeçalho Authorization: Bearer <token>."}
```

O cabeçalho `WWW-Authenticate` foi acrescentado pelo `HttpBearer`: é o jeito do HTTP dizer ao cliente qual esquema de autenticação usar. O nome do realm muda com `withRealm()`.

Com o token de exemplo:

```bash
curl -H "Authorization: Bearer token-de-exemplo-nao-use-em-producao" http://127.0.0.1:8080/api/v1/eu
```

```json
{"id":1,"cliente":"Cliente de exemplo"}
```

Com o token expirado (`token-de-exemplo-expirado`), a resposta é o mesmo `401` da primeira requisição. O cliente não fica sabendo se o token não existe ou se expirou, o que não ajuda quem está tentando adivinhar tokens.

As rotas de escrita agora pedem token:

```bash
curl -i -X POST -H "Content-Type: application/json" -d '{"titulo":"Sem token"}' http://127.0.0.1:8080/api/v1/tarefas
```

```plaintext
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json; charset=UTF-8
WWW-Authenticate: Bearer realm="api"
```

```bash
curl -i -X POST -H "Content-Type: application/json" \
  -H "Authorization: Bearer token-de-exemplo-nao-use-em-producao" \
  -d '{"titulo":"Ler a RFC 9457"}' http://127.0.0.1:8080/api/v1/tarefas
```

```plaintext
HTTP/1.1 201 Created
Content-Type: application/json; charset=UTF-8
Location: /api/v1/tarefas/10
Content-Length: 92

{"id":10,"titulo":"Ler a RFC 9457","concluida":false,"criadaEm":"2026-10-08T02:11:32-03:00"}
```

As leituras continuam abertas: `curl http://127.0.0.1:8080/api/v1/tarefas` funciona sem token.

## Cuidados

- Use HTTPS. O token vai em texto puro no cabeçalho; sem criptografia no caminho, qualquer um entre o cliente e o servidor pode copiá-lo.
- Dê prazo de validade aos tokens e permita revogá-los. Um token sem prazo que vaza vale para sempre.
- Não grave o cabeçalho `Authorization` em logs.
- Evite o `QueryParameter` quando puder: a URL, com o token dentro, acaba em logs de servidor, histórico do navegador e cabeçalhos `Referer`.
- Para limitar quantas requisições cada cliente faz, o pacote `yiisoft/rate-limiter` tem o middleware `LimitRequestsMiddleware`.

## Comparando com o Yii2

No Yii2, a autenticação de API era um filtro, como `yii\filters\auth\HttpBearerAuth`, configurado em `behaviors()` do controller. A identidade vinha do método estático `findIdentityByAccessToken()` da classe de usuário, e o resultado ficava em `Yii::$app->user`. No Yii3:

- A autenticação é o middleware `Authentication`, aplicado a rotas ou grupos.
- A busca pelo token fica num repositório que implementa `IdentityWithTokenRepositoryInterface`, separado da classe de identidade.
- A identidade chega à action como atributo da requisição.
