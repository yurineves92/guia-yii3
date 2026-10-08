# Visão geral

Segurança num app web é uma soma de cuidados pequenos: conferir cada requisição que altera dados, nunca jogar texto do usuário direto no HTML ou no SQL, guardar senhas do jeito certo e controlar quem pode fazer o quê. O template do Yii3 já resolve parte disso sem que você precise fazer nada. Este capítulo mostra o que vem pronto e como evitar as três falhas mais comuns em apps web: CSRF, XSS e SQL injection. Os outros capítulos da seção tratam de [autenticação](/seguranca/autenticacao), [autorização](/seguranca/autorizacao), [senhas](/seguranca/senhas), [criptografia](/seguranca/criptografia) e [boas práticas](/seguranca/boas-praticas) antes de publicar.

## O que o template já protege

A pilha global de middlewares do template, em `config/web/di/application.php`, tem cinco itens. Três deles têm papel direto na segurança:

- `ErrorCatcher` captura qualquer exceção e mostra uma página de erro. Com `APP_DEBUG=false`, a página não mostra o stack trace nem trechos de código.
- `SessionMiddleware` abre a sessão. O cookie de sessão sai com `HttpOnly` (o JavaScript da página não consegue lê-lo) e `SameSite=Lax` (o navegador não o envia em POSTs vindos de outros sites).
- `CsrfTokenMiddleware` recusa com o erro 422 qualquer requisição `POST`, `PUT`, `PATCH` ou `DELETE` sem um token CSRF válido.

O cookie de sessão de um app recém-criado fica assim:

```plaintext
Set-Cookie: PHPSESSID=4v1vikmkiofdgkfkddm3f15h5k; Domain=127.0.0.1; Path=/; HttpOnly; SameSite=Lax
```

Falta o `Secure`, que impede o envio do cookie por HTTP sem criptografia. O pacote `yiisoft/session` liga essa opção por padrão, mas os parâmetros que vêm com ele a desligam (`'cookie_secure' => 0`), para o app funcionar em `http://localhost`. Em produção, com HTTPS, ligue de novo nos parâmetros do app:

```php
'yiisoft/session' => [
    'session' => [
        'options' => ['cookie_secure' => 1],
    ],
],
```

Além dos middlewares, o resto da proteção depende de como você escreve o código: os helpers de HTML codificam o que recebem, e o query builder separa o SQL dos valores. As seções a seguir mostram os dois.

## CSRF

Um ataque de CSRF (*cross-site request forgery*) é um site malicioso fazendo o navegador da vítima enviar um formulário para o seu app. O navegador manda junto o cookie de sessão, então o app acha que foi a própria vítima que enviou.

A defesa é exigir em todo envio um token que só as páginas do seu app conhecem. O `CsrfTokenMiddleware` guarda um token aleatório na sessão e confere se a requisição trouxe o mesmo valor, no campo `_csrf` do corpo ou no header `X-CSRF-Token` (útil em requisições feitas com JavaScript). O token impresso na página é mascarado com um valor aleatório diferente a cada requisição, por isso ele muda a cada carregamento mesmo com a sessão igual.

Nas views, o `->csrf($csrf)` do `Html::form()` adiciona o campo escondido. A variável `$csrf` está disponível em todas as views. Um POST sem o token não chega à action:

```bash
curl -i -X POST http://127.0.0.1:8080/seguranca/logout
```

```plaintext
HTTP/1.1 422 Unprocessable Entity
```

Por isso, toda ação que altera algo deve ser um POST (ou PUT, PATCH, DELETE), nunca um GET. O logout do capítulo [Autenticação](/seguranca/autenticacao) é um formulário com um botão, e não um link, por esse motivo. Os métodos `GET`, `HEAD` e `OPTIONS` não passam pela verificação.

APIs que autenticam por token no header, e não por cookie, não estão sujeitas a CSRF. O capítulo [Autenticação de APIs](/api/autenticacao) trata desse caso.

## XSS

XSS (*cross-site scripting*) acontece quando texto vindo do usuário vai para o HTML sem ser codificado. Se alguém cadastra o nome `<script>...</script>` e a página imprime esse nome como está, o script roda no navegador de quem abrir a página, com acesso à sessão da vítima.

As views do Yii3 são PHP puro, e o `<?= ?>` não codifica nada sozinho. A regra é: todo valor que veio de fora passa por um helper antes de ir para o HTML.

- Dentro do texto de uma tag, use `Html::encode()`.
- Dentro de um atributo escrito à mão, use `Html::encodeAttribute()`. O `Html::encode()` não codifica aspas, então ele não basta num atributo: um valor como `" onmouseover="alert(1)` fecharia o atributo e criaria outro.
- Melhor ainda, monte a tag com os helpers (`Html::a()`, `Html::textInput()`, `Html::img()`...) ou com o `Field`, que codificam o conteúdo e os atributos.

```php
Html::encode('" onmouseover="alert(1)');          // " onmouseover="alert(1)
Html::encodeAttribute('" onmouseover="alert(1)'); // &quot; onmouseover=&quot;alert(1)
Html::a('Perfil', '/p', ['title' => '" onmouseover="alert(1)']);
// <a title="&quot; onmouseover=&quot;alert(1)" href="/p">Perfil</a>
```

A segunda linha de defesa é o header `Content-Security-Policy`, que diz ao navegador de onde a página pode carregar scripts. Mesmo que um script injetado escape da codificação, uma política sem `'unsafe-inline'` para scripts impede que ele rode. O capítulo [Boas práticas](/seguranca/boas-praticas) configura esse header.

## SQL injection

SQL injection acontece quando um valor do usuário é colado dentro do SQL. O query builder evita isso: os valores das condições viram parâmetros, enviados ao banco separados do comando. Para ver na prática, crie uma busca de países em `src/Web/Seguranca/Busca/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Busca;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
    ) {}

    public function __invoke(#[Query('q')] string $termo = ''): ResponseInterface
    {
        $query = $this->db
            ->select(['nome', 'codigo'])
            ->from('pais')
            ->where(['like', 'nome', $termo])
            ->orderBy(['nome' => SORT_ASC]);

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'termo' => $termo,
            'paises' => $termo === '' ? [] : $query->all(),
            'sql' => $query->createCommand()->getSql(),
        ]);
    }
}
```

E a view, em `src/Web/Seguranca/Busca/template.php`, que também mostra o SQL gerado:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var string $termo
 * @var array[] $paises
 * @var string $sql
 */

$this->setTitle('Busca de países');
?>

<h1>Busca de países</h1>

<?= Html::form()->get($urlGenerator->generate('seguranca/busca'))->open() ?>
    <?= Html::textInput('q', $termo) ?>
    <?= Html::submitButton('Buscar') ?>
<?= Html::form()->close() ?>

<?php if ($termo !== ''): ?>
    <p>Resultados para: <?= Html::encode($termo) ?></p>

    <ul>
        <?php foreach ($paises as $pais): ?>
            <li><?= Html::encode($pais['nome']) ?> (<?= Html::encode($pais['codigo']) ?>)</li>
        <?php endforeach ?>
    </ul>

    <p>SQL executado: <code><?= Html::encode($sql) ?></code></p>
<?php endif ?>
```

As rotas desta seção ficam num grupo com o prefixo `/seguranca`, no arquivo de rotas:

```php
Route::get('/busca')
    ->action(Web\Seguranca\Busca\Action::class)
    ->name('seguranca/busca'),
```

Abrindo `/seguranca/busca?q=bra`, aparece o Brasil. Buscando `a' OR 1=1 --`, não aparece nada, e o SQL mostrado é o mesmo nos dois casos:

```plaintext
SELECT "nome", "codigo" FROM "pais" WHERE "nome" LIKE :qp0 ESCAPE '\' ORDER BY "nome"
```

O termo foi para o parâmetro `:qp0`, e o banco o tratou como texto a procurar. Buscando `<script>alert(1)</script>`, a página mostra o texto literalmente, porque o `Html::encode()` o transformou em `&lt;script&gt;...`.

O query builder protege os valores, mas não os nomes de colunas e tabelas. Se o usuário escolhe a ordenação, por exemplo, confira o valor contra uma lista de colunas permitidas antes de passá-lo ao `orderBy()`. E quando escrever SQL à mão, use parâmetros:

```php
$this->db->createCommand('SELECT * FROM pais WHERE codigo = :codigo', [':codigo' => $codigo])->queryOne();
```

## Comparando com o Yii2

- A proteção CSRF continua ligada por padrão, mas agora é um middleware na pilha global, e não uma configuração do componente `request`. No Yii2, o token ficava por padrão num cookie; aqui ele fica na sessão.
- O `Html::encode()` do Yii2 codificava aspas. O do Yii3 não codifica, e para atributos existe o `Html::encodeAttribute()`.
- O query builder trata os valores do mesmo jeito que no Yii2.
