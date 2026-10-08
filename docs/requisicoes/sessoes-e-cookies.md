# Sessões e cookies

HTTP não guarda estado entre uma requisição e outra. Para lembrar algo do usuário, como um carrinho, uma mensagem de "salvo com sucesso" ou uma preferência de tema, a aplicação usa a sessão, que fica no servidor, ou cookies, que ficam no navegador. Este capítulo mostra os dois no Yii3, mais as mensagens flash e os cookies assinados.

## SessionInterface

A sessão vem do pacote `yiisoft/session`, que o template já instala e configura. Ele faz duas coisas:

- O `SessionMiddleware`, na pilha global de middlewares do template, lê o ID de sessão do cookie da requisição e, no fim, grava o cookie na resposta quando necessário.
- O serviço `Yiisoft\Session\SessionInterface` dá acesso aos dados. Por baixo, ele usa a sessão nativa do PHP.

Um contador de visitas, em `src/Web/Requisicoes/Visitas/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Visitas;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Session\SessionInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private SessionInterface $session,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $visitas = (int) $this->session->get('requisicoes.visitas', 0) + 1;
        $this->session->set('requisicoes.visitas', $visitas);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Você abriu esta página $visitas vez(es) nesta sessão.\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Com a rota `Route::get('/visitas')`, chamando três vezes com o mesmo cookie, e uma vez sem:

```bash
curl -b sessao.txt -c sessao.txt http://127.0.0.1:8080/requisicoes/visitas
curl -b sessao.txt -c sessao.txt http://127.0.0.1:8080/requisicoes/visitas
curl -b sessao.txt -c sessao.txt http://127.0.0.1:8080/requisicoes/visitas
curl http://127.0.0.1:8080/requisicoes/visitas
```

```plaintext
Você abriu esta página 1 vez(es) nesta sessão.
Você abriu esta página 2 vez(es) nesta sessão.
Você abriu esta página 3 vez(es) nesta sessão.
Você abriu esta página 1 vez(es) nesta sessão.
```

Sem o cookie `PHPSESSID`, cada requisição começa uma sessão nova.

Os métodos principais da `SessionInterface`:

- `get($chave, $padrao)`, `set($chave, $valor)`, `has($chave)` e `remove($chave)`.
- `pull($chave)`: lê e remove de uma vez.
- `all()` e `clear()`: todos os valores, ou apagar todos.
- `regenerateId()`: troca o ID da sessão mantendo os dados. Chame depois do login, para evitar fixação de sessão. O pacote `yiisoft/user` já faz isso; veja [Autenticação](/seguranca/autenticacao).
- `destroy()`: apaga a sessão inteira, como num logout.

A sessão é aberta só quando você usa um desses métodos. Uma página que não toca na sessão não cria cookie.

Como a sessão é compartilhada por toda a aplicação, prefixe as chaves (`requisicoes.visitas`) para não colidir com dados de outras partes.

### Configuração

As opções da sessão ficam nos params, na chave `yiisoft/session`. O pacote define `cookie_secure` como `0` nos params, para a sessão funcionar em `http://` durante o desenvolvimento. Em produção com HTTPS, sobrescreva no seu arquivo de params:

```php
'yiisoft/session' => [
    'session' => [
        'options' => ['cookie_secure' => 1],
    ],
],
```

Com essa opção ligada, o `SessionMiddleware` recusa conexões sem HTTPS: uma requisição para `http://` que use a sessão termina numa `SessionException` com a mensagem `"cookie_secure" is on but connection is not secure`. Por isso ela fica desligada no desenvolvimento.

Os valores de `options` são as [opções de sessão do PHP](https://www.php.net/manual/pt_BR/session.configuration.php) sem o prefixo `session.`. A classe `Session` já liga por padrão `cookie_httponly`, `use_strict_mode`, `use_only_cookies` e `cookie_samesite=Lax`. A chave `handler` aceita um `SessionHandlerInterface` para guardar as sessões em outro lugar, como banco ou Redis.

## Mensagens flash

Uma mensagem flash é um valor guardado na sessão que some depois de lido. O uso clássico: o formulário é enviado por POST, a action salva, guarda "Recado salvo." e redireciona; a página seguinte mostra a mensagem uma vez.

O serviço é o `Yiisoft\Session\Flash\FlashInterface`. Crie `src/Web/Requisicoes/Recado/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Recado;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
        private FlashInterface $flash,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === Method::POST) {
            $this->flash->set('sucesso', 'Recado salvo.');

            return $this->responseFactory
                ->createResponse(Status::SEE_OTHER)
                ->withHeader(Header::LOCATION, $this->urlGenerator->generate('requisicoes/recado'));
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'mensagem' => $this->flash->get('sucesso'),
        ]);
    }
}
```

E a view, em `src/Web/Requisicoes/Recado/template.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var string|null $mensagem
 */

$this->setTitle('Recado');
?>

<h1>Recado</h1>

<?php if ($mensagem !== null): ?>
    <p class="aviso"><?= Html::encode($mensagem) ?></p>
<?php endif ?>

<?= Html::form()->post($urlGenerator->generate('requisicoes/recado'))->csrf($csrf)->open() ?>
    <?= Html::submitButton('Salvar recado') ?>
<?= Html::form()->close() ?>
```

A rota aceita os dois métodos:

```php
Route::methods([Method::GET, Method::POST], '/recado')
    ->action(Requisicoes\Recado\Action::class)
    ->name('recado'),
```

Abra `/requisicoes/recado` e clique no botão. O POST responde `303 See Other` para a mesma URL, e o GET seguinte mostra "Recado salvo.". Recarregando a página, a mensagem não aparece mais, e o navegador não pergunta se deve reenviar o formulário, porque a última requisição foi um GET.

Detalhes do `FlashInterface`:

- `set($chave, $valor)` substitui; `add($chave, $valor)` acumula valores num array, útil para várias mensagens do mesmo tipo.
- `get($chave)` devolve `null` se não houver mensagem. A mensagem é removida na requisição seguinte à leitura.
- `getAll()` devolve todas as mensagens, e é o que um layout costuma usar para mostrar avisos em qualquer página.
- O terceiro parâmetro de `set()`, `removeAfterAccess`, quando `false`, faz a mensagem sumir depois da próxima requisição mesmo que ninguém a leia.

## Cookies

Para cookies próprios, o pacote é o `yiisoft/cookies`. Um cookie é um objeto imutável `Yiisoft\Cookies\Cookie`, e o `addToResponse()` gera o header `Set-Cookie` na resposta. Para ler, a requisição traz os cookies em `getCookieParams()`, e o `CookieCollection::fromArray()` transforma isso em objetos.

Os padrões do construtor do `Cookie` são os mais seguros: `path=/`, `Secure`, `HttpOnly` e `SameSite=Lax`. Com `Secure`, o navegador só devolve o cookie em conexões HTTPS (alguns navegadores abrem exceção para `localhost`). Para mudar, use `withSecure(false)`, `withHttpOnly(false)`, `withSameSite()`, `withDomain()` e `withPath()`. A validade vem de `withMaxAge(new DateInterval('P30D'))` ou `withExpires()`; sem nenhum dos dois, o cookie dura até o navegador fechar. Para apagar, envie o cookie com `expire()`.

### Cookies assinados

Um cookie fica no navegador, e o usuário pode alterar o valor. Se a aplicação confia nele para alguma decisão, assine o cookie. O `CookieSigner` acrescenta ao valor um hash calculado com uma chave secreta; na leitura, `validate()` confere o hash e devolve o cookie com o valor limpo, ou lança `RuntimeException` se o valor foi alterado.

A chave secreta fica nos params, em `config/common/params/requisicoes.php`:

```php
<?php

declare(strict_types=1);

return [
    'requisicoes' => [
        // Em produção, defina APP_COOKIE_KEY no ambiente com um valor aleatório e longo.
        'cookieKey' => $_ENV['APP_COOKIE_KEY'] ?? 'chave-de-desenvolvimento-troque-em-producao',
    ],
];
```

E o `CookieSigner` é configurado no container, em `config/web/di/requisicoes.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Cookies\CookieSigner;

/** @var array $params */

return [
    CookieSigner::class => [
        '__construct()' => [
            'key' => $params['requisicoes']['cookieKey'],
        ],
    ],
];
```

Uma preferência de tema guardada num cookie assinado, em `src/Web/Requisicoes/Tema/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Tema;

use DateInterval;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Yiisoft\Cookies\Cookie;
use Yiisoft\Cookies\CookieCollection;
use Yiisoft\Cookies\CookieSigner;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    private const TEMAS = ['claro', 'escuro'];

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private CookieSigner $signer,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[Query('definir')] ?string $novoTema = null): ResponseInterface
    {
        if ($novoTema !== null && in_array($novoTema, self::TEMAS, true)) {
            $cookie = (new Cookie('tema', $novoTema))->withMaxAge(new DateInterval('P30D'));

            return $this->signer->sign($cookie)->addToResponse($this->texto("Tema definido: $novoTema"));
        }

        return $this->texto('Tema atual: ' . $this->lerTema($request));
    }

    private function lerTema(ServerRequestInterface $request): string
    {
        $cookie = CookieCollection::fromArray($request->getCookieParams())->get('tema');
        if ($cookie === null) {
            return 'claro (padrão)';
        }

        try {
            $tema = $this->signer->validate($cookie)->getValue();
        } catch (RuntimeException) {
            return 'claro (cookie inválido)';
        }

        return in_array($tema, self::TEMAS, true) ? $tema : 'claro (valor desconhecido)';
    }

    private function texto(string $conteudo): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($conteudo . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

Testando com a rota `Route::get('/tema')`:

```bash
curl -b tema.txt -c tema.txt "http://127.0.0.1:8080/requisicoes/tema?definir=escuro"
curl -b tema.txt -c tema.txt http://127.0.0.1:8080/requisicoes/tema
curl -b "tema=escuro" http://127.0.0.1:8080/requisicoes/tema
```

```plaintext
Tema definido: escuro
Tema atual: escuro
Tema atual: claro (cookie inválido)
```

O header enviado na primeira chamada mostra o formato do valor assinado:

```plaintext
Set-Cookie: tema=b0338b6d6bc762b3e5c8265837378c31258f2d39...c3862b0338b6d6bc762b3e5c8265837378c31escuro; Expires=...; Max-Age=2592000; Path=/; Secure; HttpOnly; SameSite=Lax
```

Na terceira chamada, o cookie foi forjado com o valor `escuro` sem assinatura, e a action não aceitou. O mesmo acontece se alguém trocar só o final do valor assinado.

Repare que `escuro` aparece no fim do valor: assinar garante que o valor não foi alterado, mas não o esconde. Para que o usuário não consiga ler o conteúdo, use o `CookieEncryptor`, do mesmo pacote, com `encrypt()` e `decrypt()`.

Se a aplicação tem vários cookies assinados ou criptografados, o `CookieMiddleware` do pacote faz isso de forma automática: você informa os nomes (ou padrões como `prefixo*`) e se cada um deve ser assinado ou criptografado, e o middleware valida os cookies da requisição e assina os da resposta. Para montar a pilha de middlewares, veja [Middleware](/estrutura/middleware).

::: warning
A chave do `CookieSigner` precisa ser secreta e diferente em cada ambiente. Quem souber a chave consegue forjar cookies válidos. O capítulo [Criptografia](/seguranca/criptografia) mostra como gerar uma chave aleatória.
:::

## Comparando com o Yii2

- `Yii::$app->session` vira o `SessionInterface` injetado; `$session['chave']` vira `get()` e `set()`.
- `Yii::$app->session->setFlash()` e `getFlash()` viram o `FlashInterface`.
- No Yii2, todos os cookies eram validados com o `cookieValidationKey` do componente `request`. No Yii3, assinar ou criptografar é uma escolha por cookie, com `CookieSigner`, `CookieEncryptor` ou o `CookieMiddleware`.
- `Yii::$app->response->cookies->add()` vira `$cookie->addToResponse($response)`.
