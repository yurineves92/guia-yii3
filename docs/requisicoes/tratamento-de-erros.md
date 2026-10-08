# Tratamento de erros

Exceções não tratadas e erros do PHP precisam virar uma resposta: em desenvolvimento, com todos os detalhes para depurar; em produção, com uma mensagem genérica que não exponha caminhos, SQL ou senhas. No Yii3, quem faz isso é o pacote `yiisoft/error-handler`, que o template já configura. Este capítulo mostra como ele funciona, como as páginas mudam por ambiente e como dar respostas próprias para exceções específicas.

## ErrorHandler e ErrorCatcher

O pacote tem duas peças principais:

- O `ErrorHandler` registra os handlers de erro e exceção do PHP, converte erros (warnings, notices) em `ErrorException`, grava o erro no log e transforma a exceção em conteúdo por meio de um renderizador.
- O `ErrorCatcher` é um middleware PSR-15. Ele envolve o resto da pilha num `try/catch`; quando algo lança exceção, ele pede ao `ThrowableResponseFactoryInterface` uma resposta `500` e a devolve.

No template, o `ErrorCatcher` é o primeiro da pilha global, em `config/web/di/application.php`. Por estar no começo, ele pega exceções de todos os middlewares e actions que vêm depois. O `ErrorHandler` é registrado pelo `HttpApplicationRunner` em `public/index.php`, antes mesmo do container existir, para cobrir erros de configuração.

Para ver na prática, crie uma action que lança exceções, em `src/Web/Requisicoes/Erro/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Erro;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Yiisoft\ErrorHandler\Exception\UserException;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    public function __invoke(#[Query] string $tipo = ''): ResponseInterface
    {
        throw match ($tipo) {
            'usuario' => new UserException('O cálculo de frete está indisponível. Tente de novo em alguns minutos.'),
            'configuracao' => new ChaveDeApiAusenteException(),
            default => new RuntimeException('Falha ao conectar em frete.exemplo.com:443.'),
        };
    }
}
```

A rota é um `Route::get('/erro')`. As outras duas exceções aparecem mais adiante.

## Formato da resposta

O `ThrowableResponseFactory` escolhe o renderizador pelo header `Accept` da requisição:

- `text/html` ou `*/*`: `HtmlRenderer`
- `application/json`: `JsonRenderer`
- `application/xml` ou `text/xml`: `XmlRenderer`
- `text/plain`: `PlainTextRenderer`

Para requisições `HEAD`, ele usa o `HeaderRenderer`, que coloca o erro só nos headers. Todos devolvem status `500`.

Pedindo JSON em desenvolvimento:

```bash
curl -H "Accept: application/json" http://127.0.0.1:8080/requisicoes/erro
```

```json
{
    "type": "RuntimeException",
    "message": "Falha ao conectar em frete.exemplo.com:443.",
    "code": 0,
    "file": "/caminho/do/app/src/Web/Requisicoes/Erro/Action.php",
    "line": 19,
    "trace": [
```

No navegador, a mesma URL mostra a página de depuração: a mensagem, o trecho do código onde a exceção foi lançada, a pilha de chamadas e os dados da requisição.

## Páginas de erro por ambiente

Cada renderizador tem dois modos: `renderVerbose()`, com todos os detalhes, e `render()`, só com uma mensagem genérica. Quem decide é o modo debug do `ErrorHandler`, e o template liga esse modo a partir da variável de ambiente `APP_DEBUG`:

```php
$runner = new HttpApplicationRunner(
    rootPath: $root,
    debug: Environment::appDebug(),
    // ...
);
```

O `.env` de desenvolvimento do template tem `APP_DEBUG=true`. Em produção, `APP_DEBUG` fica desligado (é o padrão quando a variável não existe). Para ver o modo de produção na sua máquina, suba o servidor com as variáveis trocadas:

```bash
APP_ENV=prod APP_DEBUG=false ./yii serve
```

Agora a mesma URL responde:

```bash
curl -H "Accept: application/json" http://127.0.0.1:8080/requisicoes/erro
```

```json
{"message":"An internal server error occurred."}
```

E no navegador aparece uma página simples:

```plaintext
Error
An internal server error occurred.
The above error occurred while the Web server was processing your request.
Please contact us if you think this is a server error. Thank you.
```

Nada da mensagem original chega ao cliente. Ela continua no log, porque o `ErrorHandler` registra toda exceção com `$logger->error()` antes de renderizar, nos dois modos. Veja [Logs](/requisicoes/logs).

::: warning
Nunca rode produção com `APP_DEBUG=true`. A página de depuração mostra caminhos de arquivos, trechos de código e dados da requisição, o que inclui cookies e headers.
:::

Para mudar a aparência da página de produção, o `HtmlRenderer` aceita os parâmetros `template` e `verboseTemplate` no construtor, com o caminho de um arquivo PHP seu. O template já configura o `HtmlRenderer` em `config/common/di/error-handler.php` (para os links do trace abrirem no editor), e é ali que esses parâmetros entram.

## Mensagens que o usuário pode ver

Às vezes a mensagem da exceção é exatamente o que o usuário precisa ler. Para isso existe a `UserException`: em produção, o `HtmlRenderer` mostra a mensagem dela em vez do texto genérico. Com `?tipo=usuario`, no modo de produção:

```plaintext
Yiisoft\ErrorHandler\Exception\UserException
O cálculo de frete está indisponível. Tente de novo em alguns minutos.
```

Você pode lançar `UserException` direto ou marcar uma classe sua com o atributo `#[UserException]`, sem precisar estender. Só use para mensagens escritas para o usuário final, nunca para mensagens que podem conter dados internos. O `JsonRenderer` não faz essa distinção e responde sempre a mensagem genérica em produção.

## Exceções amigáveis

O pacote `yiisoft/friendly-exception` define a `FriendlyExceptionInterface`, com dois métodos: `getName()`, um título legível, e `getSolution()`, um texto em Markdown explicando como resolver. A página de depuração do `HtmlRenderer` usa o nome como título e mostra a solução formatada. É um recurso para quem desenvolve: em produção, a página continua genérica.

Exceções de configuração são boas candidatas. Crie `src/Web/Requisicoes/Erro/ChaveDeApiAusenteException.php`:

````php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Erro;

use RuntimeException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;

final class ChaveDeApiAusenteException extends RuntimeException implements FriendlyExceptionInterface
{
    public function __construct()
    {
        parent::__construct('A chave da API de frete não foi configurada.');
    }

    public function getName(): string
    {
        return 'Chave da API de frete ausente';
    }

    public function getSolution(): ?string
    {
        return <<<'MD'
            Defina a variável de ambiente `FRETE_API_KEY` no arquivo `.env`:

            ```bash
            FRETE_API_KEY=sua-chave-aqui
            ```

            Depois reinicie o servidor.
            MD;
    }
}
````

Em desenvolvimento, `/requisicoes/erro?tipo=configuracao` mostra "Chave da API de frete ausente" no título da página e o passo a passo logo abaixo. Vários pacotes do Yii3 usam esse recurso nos próprios erros de configuração.

## Respostas próprias para exceções

Nem toda exceção é um erro 500. Se o código lança `ArtigoNaoEncontradoException` quando o artigo não existe, a resposta certa é um 404. O middleware `ExceptionResponder`, do mesmo pacote, mapeia classes de exceção para respostas.

A exceção, em `src/Requisicoes/ArtigoNaoEncontradoException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Requisicoes;

use RuntimeException;

final class ArtigoNaoEncontradoException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Artigo $id não encontrado.");
    }
}
```

A action, em `src/Web/Requisicoes/Publicado/Action.php`, só conhece o artigo 1:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Publicado;

use App\Requisicoes\ArtigoNaoEncontradoException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        if ($id !== 1) {
            throw new ArtigoNaoEncontradoException($id);
        }

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Artigo $id: Olá, Yii3\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

E a rota coloca o `ExceptionResponder` antes da action:

```php
use App\Requisicoes\ArtigoNaoEncontradoException;
use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\ErrorHandler\Middleware\ExceptionResponder;

Route::get('/publicados/{id:\d+}')
    ->middleware([
        'class' => ExceptionResponder::class,
        '__construct()' => [
            'exceptionMap' => [
                ArtigoNaoEncontradoException::class => static fn(
                    NotFoundHandler $notFoundHandler,
                    ServerRequestInterface $request,
                ) => $notFoundHandler->handle($request),
            ],
        ],
    ])
    ->action(Requisicoes\Publicado\Action::class)
    ->name('publicado'),
```

O `exceptionMap` aceita dois formatos de valor:

- Um código de status, como `ArtigoNaoEncontradoException::class => Status::NOT_FOUND`. A resposta sai com o status e corpo vazio.
- Uma função que devolve a resposta. Os parâmetros dela são resolvidos pelo container, e os nomes `$exception` e `$request` recebem a exceção e a requisição. Aqui ela reaproveita o `NotFoundHandler` do template, então o 404 tem a mesma página das URLs inexistentes.

`/requisicoes/publicados/1` responde `200`, e `/requisicoes/publicados/2` responde `404` com a página "The page /requisicoes/publicados/2 not found.". Exceções que não estão no mapa seguem adiante até o `ErrorCatcher`.

Aqui o `ExceptionResponder` está numa rota só. Para valer na aplicação inteira, ele entra na pilha global logo depois do `ErrorCatcher`; a pilha é assunto do capítulo [Middleware](/estrutura/middleware). Para APIs, o formato de erro mais usado é o Problem Details, tratado em [Erros](/api/erros).

## Comparando com o Yii2

- O `ErrorHandler` do Yii2 com `errorAction` (`site/error`) vira o `ErrorCatcher` com renderizadores. Não existe uma action de erro.
- `YII_DEBUG` vira a variável `APP_DEBUG`, lida pela classe `App\Environment` do template.
- A `yii\base\UserException` do Yii2 continua existindo como ideia, agora em `Yiisoft\ErrorHandler\Exception\UserException`.
- O `NotFoundHttpException` e as outras exceções HTTP do Yii2 não existem no núcleo do Yii3. Lance exceções do seu domínio e mapeie para status com o `ExceptionResponder`, ou devolva a resposta com o status certo na própria action.
