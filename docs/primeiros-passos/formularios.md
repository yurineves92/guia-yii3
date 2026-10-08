# Trabalhando com formulários

Neste capítulo, vamos criar um formulário de cadastro com nome e e-mail. Ele valida os dados e, se estiver tudo certo, mostra uma página de confirmação. Ainda não vamos salvar nada no banco: isso fica para o próximo capítulo.

## Instalando o pacote de formulários

O template não vem com formulários. Instale o `yiisoft/form-model`, que traz a classe de formulário, a validação e os widgets de campo:

```bash
composer require yiisoft/form-model
```

## O formulário

Um formulário no Yii3 é uma classe que estende `FormModel`. Cada campo é uma propriedade pública, e as regras de validação são atributos em cima dela. Crie `src/Web/Cadastro/CadastroForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Cadastro;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Required;

final class CadastroForm extends FormModel
{
    #[Required(message: 'Informe o seu nome.')]
    public string $nome = '';

    #[Required(message: 'Informe o seu e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    public function getPropertyLabels(): array
    {
        return [
            'nome' => 'Nome',
            'email' => 'E-mail',
        ];
    }
}
```

Algumas observações:

- As mensagens padrão das regras estão em inglês. Passando `message:`, você escreve a sua.
- O `skipOnEmpty: true` no `Email` evita mostrar dois erros quando o campo está vazio. O `Required` já avisa.
- O `getPropertyLabels()` define os rótulos que aparecem no formulário. Sem ele, o rótulo é gerado a partir do nome da propriedade.

## A action

Crie `src/Web/Cadastro/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Cadastro;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\FormModel\FormHydrator;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private FormHydrator $formHydrator,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $form = new CadastroForm();

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            return $this->viewRenderer->render(__DIR__ . '/confirmacao', ['form' => $form]);
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
```

A mesma action atende o GET (mostrar o formulário) e o POST (receber os dados). O `populateFromPostAndValidate()` preenche o formulário com o que veio no POST e valida. Ele retorna `true` só quando havia dados e eles são válidos. Nos outros casos (primeiro acesso ou dados inválidos), mostramos o formulário. Se a validação falhou, o objeto `$form` já carrega o que o usuário digitou e os erros, então a view só precisa exibir.

## As views

Crie `src/Web/Cadastro/formulario.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Cadastro\CadastroForm $form
 */

$this->setTitle('Cadastro');
?>

<h1>Cadastro</h1>

<?= Html::form()->post($urlGenerator->generate('cadastro'))->csrf($csrf)->open() ?>
    <?= Field::text($form, 'nome') ?>
    <?= Field::email($form, 'email') ?>
    <?= Field::submitButton('Enviar') ?>
<?= Html::form()->close() ?>
```

O `Field::text($form, 'nome')` gera o rótulo, o campo com o valor atual e as mensagens de erro, tudo de uma vez. O nome do campo no HTML fica `CadastroForm[nome]`, e é assim que o `FormHydrator` sabe onde encontrar o valor no POST.

O `->csrf($csrf)` adiciona um campo escondido com o token CSRF. O template já protege todo POST com o `CsrfTokenMiddleware`, então um formulário sem esse campo é recusado com o erro 422. As variáveis `$urlGenerator` e `$csrf` estão disponíveis em todas as views, sem que a action precise passar.

E a confirmação, em `src/Web/Cadastro/confirmacao.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var App\Web\Cadastro\CadastroForm $form
 */

$this->setTitle('Cadastro recebido');
?>

<h1>Recebemos o seu cadastro</h1>

<ul>
    <li><strong>Nome:</strong> <?= Html::encode($form->nome) ?></li>
    <li><strong>E-mail:</strong> <?= Html::encode($form->email) ?></li>
</ul>
```

## A rota

Como a action responde a GET e POST, a rota precisa aceitar os dois métodos:

```php
use Yiisoft\Http\Method;

Route::methods([Method::GET, Method::POST], '/cadastro')
    ->action(Web\Cadastro\Action::class)
    ->name('cadastro'),
```

## Testando

Abra http://127.0.0.1:8080/cadastro.

- Enviando com o nome vazio e o e-mail `nao-e-email`, o formulário volta com "Informe o seu nome." e "Este e-mail não parece válido.". O e-mail digitado continua no campo.
- Enviando um nome e um e-mail válidos, aparece a página "Recebemos o seu cadastro" com os dados.

## Comparando com o Yii2

No Yii2, o formulário seria um `Model` com o método `rules()`, e a view usaria o `ActiveForm`. As ideias continuam as mesmas, mas:

- As regras ficam em atributos, perto da propriedade que validam.
- O `FormHydrator` faz o papel do `$model->load()` seguido do `$model->validate()`.
- O formulário não sabe nada sobre HTTP. Quem lê a requisição é a action.
