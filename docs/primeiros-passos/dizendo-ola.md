# Dizendo "Olá"

Vamos criar a primeira página: ela mostra uma mensagem que vem da URL. Se você acessar `/ola?mensagem=Bom dia`, a página mostra "Bom dia". Sem o parâmetro, mostra "Olá".

São três peças: uma action, uma view e uma rota.

## A action

Crie `src/Web/Ola/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Ola;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(#[Query('mensagem')] ?string $mensagem = null): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'mensagem' => $mensagem ?? 'Olá',
        ]);
    }
}
```

O `WebViewRenderer` chega pelo construtor. Você não precisa configurar nada para isso: o container vê o tipo e entrega a instância.

O atributo `#[Query('mensagem')]` diz que o parâmetro `$mensagem` vem da query string da URL. Se ele não estiver lá, vale o padrão `null`. Existem atributos parecidos para outras origens, como `#[Body]` para o corpo da requisição e `#[RouteArgument]` para partes da rota.

O `render()` recebe o caminho da view, sem a extensão `.php`, e as variáveis que ela vai usar. O resultado já é uma resposta HTTP pronta, com a view dentro do layout do template.

## A view

Crie `src/Web/Ola/template.php`, na mesma pasta:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $mensagem
 */

$this->setTitle('Olá');
?>

<h1><?= Html::encode($mensagem) ?></h1>
```

Views são arquivos PHP comuns. O bloco `@var` no topo não é obrigatório, mas faz o editor entender as variáveis.

O `Html::encode()` é importante: a mensagem vem da URL, então qualquer um pode mandar HTML ou JavaScript ali. Codificando, `<script>` vira texto e não é executado. Use sempre que for imprimir algo que veio do usuário.

## A rota

Abra `config/common/routes.php` e adicione a rota ao lado da página inicial:

```php
Route::get('/ola')
    ->action(Web\Ola\Action::class)
    ->name('ola'),
```

O nome (`ola`) serve para gerar URLs sem escrever o caminho na mão: `$urlGenerator->generate('ola')` devolve `/ola`. Se um dia a URL mudar, os links acompanham.

## Testando

Acesse:

- http://127.0.0.1:8080/ola mostra "Olá"
- http://127.0.0.1:8080/ola?mensagem=Bom%20dia mostra "Bom dia"
- http://127.0.0.1:8080/ola?mensagem=%3Cb%3Eoi%3C/b%3E mostra o texto `<b>oi</b>` literalmente, sem negrito, porque o `Html::encode()` fez o trabalho dele

## O que aconteceu

Comparando com o Yii2, que tinha `actionSay($message = 'Hello')` dentro de um `SiteController`:

- Não existe controller base. A action é uma classe com `__invoke()`.
- O parâmetro não vem "por mágica" do nome: o atributo `#[Query]` diz de onde ele sai.
- A rota é declarada explicitamente, em vez de ser deduzida do nome do controller e da action.

No próximo capítulo, vamos receber dados de um formulário e validar.
