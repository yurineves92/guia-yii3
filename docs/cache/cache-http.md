# Cache HTTP

O cache de dados economiza trabalho no servidor. O cache HTTP economiza a viagem: o navegador, ou um proxy no caminho, guarda a resposta e evita pedir de novo, ou pergunta ao servidor se o que tem ainda vale e recebe um `304 Not Modified` sem corpo. Tudo isso é controlado por cabeçalhos da resposta, e este capítulo mostra como enviá-los no Yii3, primeiro à mão e depois com o middleware `HttpCache`.

## Os cabeçalhos

O `Cache-Control` diz quem pode guardar a resposta e por quanto tempo. As diretivas mais usadas:

- `max-age=300`: a resposta pode ser usada por 300 segundos sem consultar o servidor.
- `public`: qualquer cache pode guardar, inclusive proxies e CDNs.
- `private`: só o navegador do usuário pode guardar. Use em páginas com dados da pessoa logada.
- `no-cache`: pode guardar, mas precisa confirmar com o servidor antes de cada uso. Apesar do nome, não proíbe o cache.
- `no-store`: não guarde em lugar nenhum. Para dados sensíveis.

Os outros dois cabeçalhos servem para essa confirmação, chamada de revalidação:

- `ETag` é uma "impressão digital" do conteúdo. O navegador guarda o valor e, na próxima vez, envia `If-None-Match` com ele. Se o conteúdo não mudou, o servidor responde `304`.
- `Last-Modified` é a data da última alteração. O navegador devolve a data em `If-Modified-Since`, e o servidor responde `304` se nada mudou depois dela.

O `ETag` é mais preciso, porque não depende de relógio nem de resolução de um segundo. O `Last-Modified` é mais simples quando você já tem uma data de alteração à mão.

## ETag na action

Vamos devolver um país em JSON, com `ETag`. Crie `src/Web/Cache/Http/PaisAction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Cache\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class PaisAction
{
    public function __construct(
        private ConnectionInterface $db,
        private ResponseFactoryInterface $responseFactory,
        private JsonResponseFactory $jsonResponseFactory,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        #[RouteArgument('codigo')] string $codigo,
    ): ResponseInterface {
        $pais = $this->db->select(['codigo', 'nome', 'populacao'])
            ->from('pais')
            ->where(['codigo' => $codigo])
            ->one();

        if ($pais === null) {
            return $this->responseFactory->createResponse(Status::NOT_FOUND);
        }

        $pais['populacao'] = (int) $pais['populacao'];

        $etag = '"' . md5(serialize($pais)) . '"';

        if ($request->getHeaderLine(Header::IF_NONE_MATCH) === $etag) {
            $response = $this->responseFactory->createResponse(Status::NOT_MODIFIED);
        } else {
            $response = $this->jsonResponseFactory->createResponse($pais);
        }

        return $response
            ->withHeader(Header::ETAG, $etag)
            ->withHeader(Header::CACHE_CONTROL, 'no-cache');
    }
}
```

- O `ETag` é o MD5 dos dados do país, entre aspas, como pede a especificação. Mudou um campo, muda o `ETag`.
- Se o cliente mandou `If-None-Match` com o mesmo valor, a resposta é um `304` vazio. Senão, vai o JSON completo.
- Nos dois casos, a resposta leva o `ETag` e `Cache-Control: no-cache`, para que o navegador sempre pergunte antes de reaproveitar.
- O `JsonResponseFactory`, do pacote `yiisoft/data-response`, transforma o array em JSON e põe o `Content-Type`. Ele aparece com mais detalhes em [APIs REST](/api/inicio-rapido).
- As constantes `Header` e `Status`, do `yiisoft/http`, evitam erro de digitação nos nomes dos cabeçalhos e nos códigos.

A comparação com `===` é a forma mais simples e atende ao navegador, que devolve exatamente o `ETag` recebido. A especificação permite que o `If-None-Match` traga uma lista de valores separados por vírgula, ou `*`. O middleware mostrado adiante trata a lista.

A rota, no grupo `/cache` do arquivo de rotas:

```php
Route::get('/http/paises/{codigo:[A-Z]{2}}')
    ->action(Cache\Http\PaisAction::class)
    ->name('http/pais'),
```

### Testando

```bash
curl -i http://127.0.0.1:8080/cache/http/paises/BR
```

```plaintext
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8
ETag: "0bf128bf61e43df558e68838c9c6eb35"
Cache-Control: no-cache
Content-Length: 53

{"codigo":"BR","nome":"Brasil","populacao":212000000}
```

Agora, fazendo o papel do navegador, mande o `ETag` de volta:

```bash
curl -i -H 'If-None-Match: "0bf128bf61e43df558e68838c9c6eb35"' http://127.0.0.1:8080/cache/http/paises/BR
```

```plaintext
HTTP/1.1 304 Not Modified
ETag: "0bf128bf61e43df558e68838c9c6eb35"
Cache-Control: no-cache
```

Sem corpo. Para um JSON de uma linha, a diferença é pequena. Para uma listagem grande ou uma imagem, o `304` economiza banda dos dois lados.

Repare que a consulta ao banco rodou nas duas requisições. O `ETag` precisa ser calculado a partir do conteúdo, então o servidor ainda faz parte do trabalho. Se a consulta é cara, combine com o [cache de dados](/cache/cache-de-dados), ou calcule o `ETag` a partir de algo mais barato, como uma coluna de versão ou de data de alteração.

## O middleware HttpCache

O `yiisoft/yii-middleware` traz o middleware `Yiisoft\Yii\Middleware\HttpCache`, que faz esse trabalho para você. Você informa como obter o `ETag` (a partir de uma "semente") e/ou o `Last-Modified`, e ele:

- calcula o `ETag` com `withEtagSeed()` ou a data com `withLastModified()`;
- chama a action;
- acrescenta `Cache-Control`, `ETag` e `Last-Modified` à resposta;
- troca o status para `304` quando o `If-None-Match` ou o `If-Modified-Since` da requisição batem.

Ele só age em `GET` e `HEAD`. O `Cache-Control` padrão é `public, max-age=3600`, e você troca com `withCacheControlHeader()`.

::: warning HttpCache está marcado como obsoleto
Na versão instalada, a classe tem a anotação `@deprecated` e recomenda o `HttpCacheMiddleware` do pacote `yiisoft/http-middleware`. Esse pacote não faz parte do template nem do app de exemplo. O `HttpCache` continua funcionando, e o funcionamento descrito aqui é o mesmo; se for usar o pacote novo, confira a documentação dele.
:::

Como exemplo, vamos servir um aviso guardado num arquivo de texto, usando a data de modificação do arquivo como `Last-Modified`. A action, em `src/Web/Cache/Http/AvisoAction.php`, só lê o arquivo:

```php
<?php

declare(strict_types=1);

namespace App\Web\Cache\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;

final readonly class AvisoAction
{
    public const ARQUIVO = __DIR__ . '/aviso.txt';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'text/plain; charset=utf-8');
        $response->getBody()->write(file_get_contents(self::ARQUIVO));

        return $response;
    }
}
```

O cache HTTP fica todo na rota:

```php
use Yiisoft\Yii\Middleware\HttpCache;

Route::get('/http/aviso')
    ->middleware(
        static fn () => (new HttpCache())
            ->withLastModified(static fn () => filemtime(Cache\Http\AvisoAction::ARQUIVO))
            ->withCacheControlHeader('public, max-age=300'),
    )
    ->action(Cache\Http\AvisoAction::class)
    ->name('http/aviso'),
```

O `HttpCache` é configurado com métodos `with*()`, então não dá para só passar o nome da classe no `middleware()`. Aqui passamos uma função que monta o middleware já configurado. O roteador aceita isso: quando a função devolve um middleware, ele é executado em seguida. O callback de `withLastModified()` recebe a requisição e deve devolver um timestamp Unix. O de `withEtagSeed()` recebe a requisição e devolve uma string, que o middleware transforma num `ETag` com SHA-1.

### Testando

```bash
curl -i http://127.0.0.1:8080/cache/http/aviso
```

```plaintext
HTTP/1.1 200 OK
Content-Type: text/plain; charset=utf-8
Cache-Control: public, max-age=300
Last-Modified: Thu, 08 Oct 2026 04:59:31 GMT
Content-Length: 42

A loja fecha no feriado de 12 de outubro.
```

Com a data de volta em `If-Modified-Since`:

```bash
curl -i -H "If-Modified-Since: Thu, 08 Oct 2026 04:59:31 GMT" http://127.0.0.1:8080/cache/http/aviso
```

```plaintext
HTTP/1.1 304 Not Modified
Content-Type: text/plain; charset=utf-8
Cache-Control: public, max-age=300
Last-Modified: Thu, 08 Oct 2026 04:59:31 GMT
```

Edite o `aviso.txt` (ou rode `touch` nele) e repita a última requisição: a resposta volta a ser `200` com o texto, porque o arquivo agora é mais novo que a data enviada.

Durante os 300 segundos do `max-age`, um navegador nem chega a fazer a requisição. Depois disso, ele revalida com `If-Modified-Since`.

Um detalhe do `HttpCache`: ele chama a action mesmo quando a resposta vai ser `304`, e só depois troca o status. O ganho é na transferência, não no processamento. Quando montar a resposta é caro, a versão à mão, como na `PaisAction`, deixa você pular esse trabalho.

## Cuidados

- Não use `public` em respostas que dependem do usuário logado. Um proxy compartilhado poderia entregar a página de uma pessoa para outra. Use `private`, ou `no-store` para dados sensíveis.
- O `ETag` precisa mudar sempre que o conteúdo muda. Se a resposta depende de algo além dos dados (idioma, usuário, formato), esse algo precisa entrar no cálculo.
- `max-age` alto em conteúdo que muda é difícil de desfazer: o navegador não pergunta ao servidor até o prazo acabar.

## Comparando com o Yii2

No Yii2, o mesmo trabalho era feito pelo filtro `yii\filters\HttpCache`, configurado em `behaviors()` do controller com `lastModified`, `etagSeed` e `cacheControlHeader`. O `HttpCache` do Yii3 tem as mesmas opções, agora como métodos `with*()`, e é um middleware PSR-15 aplicado na rota.
