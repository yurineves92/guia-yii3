# Resposta

Toda action do Yii3 devolve um objeto de resposta. Não existe `echo` nem `return 'texto'`: você cria uma resposta PSR-7, escreve o corpo e define status e headers. Este capítulo mostra como montar respostas de texto, redirecionamentos, JSON e downloads.

## ResponseFactoryInterface

A resposta implementa `Psr\Http\Message\ResponseInterface`, da [PSR-7](https://www.php-fig.org/psr/psr-7/). Para criar uma, peça a fábrica `Psr\Http\Message\ResponseFactoryInterface` (da PSR-17) no construtor. O template já liga essa interface à implementação do `httpsoft/http-message`, em `config/web/di/psr17.php`.

Uma action que responde `201 Created` com alguns headers, em `src/Web/Requisicoes/Criar/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Criar;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(Status::CREATED);
        $response->getBody()->write('Artigo 7 criado.');

        return $response
            ->withHeader(Header::CONTENT_TYPE, 'text/plain; charset=utf-8')
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('requisicoes/artigo', ['id' => 7]))
            ->withHeader('X-Exemplo', 'requisicoes');
    }
}
```

A rota é um `Route::post('/artigos')`. Chamando com o token CSRF (veja [Requisição](/requisicoes/requisicao) para obter o `$TOKEN`):

```bash
curl -i -b cookies.txt -H "X-CSRF-Token: $TOKEN" -X POST http://127.0.0.1:8080/requisicoes/artigos
```

```plaintext
HTTP/1.1 201 Created
Content-Type: text/plain; charset=utf-8
Location: /requisicoes/artigos/7
X-Exemplo: requisicoes
Content-Length: 16

Artigo 7 criado.
```

Alguns pontos:

- `createResponse()` recebe o status. Sem argumento, é `200`.
- As classes `Status` e `Header`, do pacote `yiisoft/http`, têm constantes para os códigos e nomes de header. Não são obrigatórias, mas evitam erro de digitação.
- `withHeader()` devolve uma resposta nova. Se você chamar sem guardar o resultado, o header se perde. O capítulo [Imutabilidade](/conceitos/imutabilidade) explica o motivo.
- `getBody()` devolve o stream do corpo, e o `write()` escreve nele. O stream é a exceção à imutabilidade: escrever nele altera o mesmo objeto.

Para páginas HTML, você não monta a resposta na mão: o `WebViewRenderer::render()` já devolve uma `ResponseInterface` com a view dentro do layout. O capítulo [Views](/estrutura/views) cobre isso. Como o retorno é uma resposta comum, dá para ajustar depois, como o `NotFoundHandler` do template faz com `->withStatus(Status::NOT_FOUND)`.

## Redirecionamentos

Um redirect é uma resposta `3xx` com o header `Location`. Crie `src/Web/Requisicoes/Redirecionar/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Redirecionar;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('requisicoes/artigo', ['id' => 1]));
    }
}
```

```bash
curl -i http://127.0.0.1:8080/requisicoes/redirecionar
```

```plaintext
HTTP/1.1 302 Found
Location: /requisicoes/artigos/1
```

Qual status usar:

- `Status::FOUND` (302): redirecionamento temporário comum.
- `Status::SEE_OTHER` (303): depois de processar um POST, para o navegador fazer um GET na página de destino. É o padrão "post, redirect, get", usado no capítulo [Sessões e cookies](/requisicoes/sessoes-e-cookies).
- `Status::MOVED_PERMANENTLY` (301): o endereço mudou de vez, e navegadores e buscadores podem guardar o destino.

Quando uma URL antiga só precisa apontar para outra, nem é preciso escrever uma action. O middleware `Redirect`, do pacote `yiisoft/yii-middleware`, faz isso na própria rota:

```php
use Yiisoft\Yii\Middleware\Redirect;

Route::get('/endereco-antigo')
    ->action(static fn(Redirect $redirect) => $redirect->toRoute('requisicoes/links'))
    ->name('endereco-antigo'),
```

A closure recebe o middleware do container, configura o destino e o devolve, e o router executa o middleware devolvido. O padrão do `Redirect` é `301`; use `->temporary()` para `302` ou `->toUrl()` para um endereço fixo.

```plaintext
HTTP/1.1 301 Moved Permanently
Location: /requisicoes/links
```

## JSON

Dá para escrever `json_encode()` no corpo e definir o `Content-Type` na mão, mas o pacote `yiisoft/data-response` já faz isso. Ele vem instalado no template como dependência do `yiisoft/yii-view-renderer`. Se o seu código usa as classes dele diretamente, declare o pacote no seu `composer.json` com `composer require yiisoft/data-response`, para não depender de uma dependência indireta.

Crie `src/Web/Requisicoes/Json/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Json;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $jsonResponseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->jsonResponseFactory->createResponse([
            'id' => 42,
            'titulo' => 'Configuração no Yii3',
            'tags' => ['yii3', 'config'],
            'publicado' => true,
        ]);
    }
}
```

```bash
curl -i http://127.0.0.1:8080/requisicoes/json
```

```plaintext
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8
Content-Length: 85

{"id":42,"titulo":"Configuração no Yii3","tags":["yii3","config"],"publicado":true}
```

O `createResponse()` aceita o status como segundo parâmetro: `createResponse($dados, Status::CREATED)`. O `JsonFormatter` usado por baixo grava acentos e barras sem escapar (`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`). Para mudar as opções, configure o `JsonFormatter` no container; veja [Container de DI](/conceitos/container-di).

Há fábricas equivalentes para outros formatos: `XmlResponseFactory`, `HtmlResponseFactory` e `PlainTextResponseFactory`.

## Downloads

Para o navegador baixar o conteúdo em vez de exibir, use o header `Content-Disposition: attachment`. O `ContentDispositionHeader`, do `yiisoft/http`, monta esse header tratando nomes com acento. Crie `src/Web/Requisicoes/Download/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Download;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Http\ContentDispositionHeader;
use Yiisoft\Http\Header;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $csv = "codigo;nome\nBR;Brasil\nPT;Portugal\n";

        return $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'text/csv; charset=utf-8')
            ->withHeader(ContentDispositionHeader::name(), ContentDispositionHeader::attachment('países.csv'))
            ->withBody($this->streamFactory->createStream($csv));
    }
}
```

```plaintext
HTTP/1.1 200 OK
Content-Type: text/csv; charset=utf-8
Content-Disposition: attachment; filename="paises.csv"; filename*=utf-8''pa%C3%ADses.csv

codigo;nome
BR;Brasil
PT;Portugal
```

O header traz duas versões do nome: `filename` sem acento, para clientes antigos, e `filename*` com o nome original codificado.

Aqui o conteúdo foi gerado em memória com `createStream()`. Para um arquivo que já está no disco, use `$this->streamFactory->createStreamFromFile($caminho)`: o arquivo é lido aos poucos na hora de enviar, sem carregar tudo na memória. Para exibir no navegador em vez de baixar (um PDF, por exemplo), troque `attachment()` por `inline()`.

## DataResponse

Nas versões anteriores do `yiisoft/data-response`, o centro do pacote era a classe `DataResponse`: uma resposta que carregava os dados crus e só os formatava (em JSON, XML ou HTML) no fim, por um middleware. Isso permitia que a mesma action servisse formatos diferentes.

Na versão 2.2, instalada no template, `DataResponse`, a antiga `DataResponseFactory` (no namespace `Yiisoft\DataResponse`) e os formatadores `*DataResponseFormatter` estão marcados como obsoletos. A ideia continua, com outras classes: o corpo da resposta passa a ser um `DataStream`, que guarda os dados e só os formata quando é lido.

A action cria a resposta com `DataResponseFactoryInterface`, sem escolher formato. Crie `src/Web/Requisicoes/Dados/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Dados;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

final readonly class Action
{
    public function __construct(
        private DataResponseFactoryInterface $dataResponseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->dataResponseFactory->createResponse([
            'id' => 42,
            'titulo' => 'Configuração no Yii3',
        ]);
    }
}
```

Quem escolhe o formato é um middleware na rota. O `ContentNegotiatorDataResponseMiddleware` olha o header `Accept` da requisição:

```php
use Yiisoft\DataResponse\Formatter\JsonFormatter;
use Yiisoft\DataResponse\Formatter\XmlFormatter;
use Yiisoft\DataResponse\Middleware\ContentNegotiatorDataResponseMiddleware;

Route::get('/dados')
    ->middleware([
        'class' => ContentNegotiatorDataResponseMiddleware::class,
        '__construct()' => [
            'formatters' => [
                'application/json' => new JsonFormatter(),
                'application/xml' => new XmlFormatter(),
            ],
            'fallback' => new JsonFormatter(),
        ],
    ])
    ->action(Requisicoes\Dados\Action::class)
    ->name('dados'),
```

O middleware é configurado com a mesma sintaxe de definição do container: `class` e os argumentos do construtor. O `fallback` vale quando nenhum tipo do `Accept` bate.

```bash
curl http://127.0.0.1:8080/requisicoes/dados
curl -H "Accept: application/xml" http://127.0.0.1:8080/requisicoes/dados
```

```plaintext
{"id":42,"titulo":"Configuração no Yii3"}
<?xml version="1.0" encoding="UTF-8"?>
<response><id>42</id><titulo>Configuração no Yii3</titulo></response>
```

O pacote também traz `JsonDataResponseMiddleware`, `XmlDataResponseMiddleware` e similares, para quando o formato é fixo, e o `ContentNegotiatorResponseFactory`, que faz a negociação na fábrica em vez do middleware.

Este formato de resposta aparece de novo na seção [APIs REST](/api/recursos).

## Comparando com o Yii2

- O `Yii::$app->response` compartilhado vira um objeto novo criado pela fábrica em cada action.
- `$this->redirect(['artigo/view', 'id' => 1])` vira uma resposta `302` com `Location`, ou o middleware `Redirect`.
- `Response::FORMAT_JSON` com `return $dados` vira o `JsonResponseFactory`.
- `$response->sendFile()` e `sendContentAsFile()` viram um stream no corpo com o header `Content-Disposition`.
