# Criando formulários

O capítulo [Trabalhando com formulários](/primeiros-passos/formularios) mostrou o caminho básico: uma classe `FormModel`, o `FormHydrator` na action e o `Field` na view. Aqui vamos ver o que mais dá para controlar: quais propriedades o formulário aceita, de onde vêm rótulos, dicas e placeholders, os tipos de campo e como mudar o HTML gerado com temas. O exemplo é um formulário de contato em `/formularios/contato`.

## O FormModel

Crie `src/Web/Formularios/Contato/ContatoForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Contato;

use Yiisoft\FormModel\Attribute\Hint;
use Yiisoft\FormModel\Attribute\Placeholder;
use Yiisoft\FormModel\Attribute\Safe;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;

final class ContatoForm extends FormModel
{
    public const ASSUNTOS = [
        'duvida' => 'Dúvida',
        'orcamento' => 'Orçamento',
        'suporte' => 'Suporte técnico',
    ];

    #[Label('Seu nome')]
    #[Placeholder('Maria da Silva')]
    #[Required(message: 'Informe o seu nome.')]
    public string $nome = '';

    #[Label('E-mail')]
    #[Hint('Usamos só para responder a esta mensagem.')]
    #[Required(message: 'Informe o seu e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    #[Required(message: 'Escolha um assunto.')]
    #[In(['duvida', 'orcamento', 'suporte'], message: 'Escolha um assunto da lista.', skipOnEmpty: true)]
    public string $assunto = '';

    #[Required(message: 'Escreva a mensagem.')]
    #[Length(min: 10, lessThanMinMessage: 'A mensagem precisa ter pelo menos {min} caracteres.', skipOnEmpty: true)]
    public string $mensagem = '';

    #[Safe]
    public bool $receberNovidades = false;

    public function getPropertyLabels(): array
    {
        return [
            'mensagem' => 'Mensagem',
            'receberNovidades' => 'Quero receber novidades por e-mail',
        ];
    }

    public function getPropertyHints(): array
    {
        return [
            'mensagem' => 'Conte o máximo de detalhes que puder.',
        ];
    }
}
```

O formulário continua sendo uma classe simples, com propriedades públicas tipadas. O nome do formulário, usado nos nomes dos campos HTML (`ContatoForm[nome]`), vem do nome curto da classe, pelo método `getFormName()`. Se quiser outro prefixo, sobrescreva esse método.

### Só entra o que tem regra

O `FormHydrator` só preenche as propriedades que têm pelo menos uma regra de validação. As outras são ignoradas, mesmo que venham no POST. Isso protege contra quem envia campos a mais. Se o seu formulário tivesse uma propriedade `$papel = 'leitor'` sem regra, um POST com `ContatoForm[papel]=admin` não mudaria nada.

Para um campo que não precisa de validação, mas deve ser preenchido, use o atributo `#[Safe]`. Ele é uma regra que sempre passa. É o caso de `receberNovidades`: um checkbox não tem o que validar, mas sem o `#[Safe]` o valor marcado pelo usuário nunca chegaria ao objeto.

### Rótulos, dicas e placeholders

Existem duas formas de definir esses textos:

- Atributos na propriedade: `#[Label]` (do pacote do validador), `#[Hint]` e `#[Placeholder]` (do `yiisoft/form-model`).
- Métodos que devolvem arrays indexados pelo nome da propriedade: `getPropertyLabels()`, `getPropertyHints()` e `getPropertyPlaceholders()`.

Se uma propriedade aparece nos dois lugares, o método vence. Sem nenhum dos dois, o rótulo é gerado a partir do nome: `assunto` vira "Assunto", e `receberNovidades` viraria "Receber Novidades".

Há uma diferença entre as duas formas. O rótulo definido com `#[Label]` também é usado nas mensagens de erro padrão do validador, no lugar de `{Property}`: com `#[Label('Seu nome')]`, a mensagem padrão do `Required` fica "Seu nome cannot be blank.". O rótulo que vem do `getPropertyLabels()` só aparece no formulário, e a mensagem continuaria usando o nome gerado, "Nome".

## A action

A action é igual à de Primeiros passos. Crie `src/Web/Formularios/Contato/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Contato;

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
        $form = new ContatoForm();

        if ($this->formHydrator->populateFromPostAndValidate($form, $request)) {
            return $this->viewRenderer->render(__DIR__ . '/enviado', ['form' => $form]);
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
```

O `FormHydrator` tem outros métodos para quando você precisa separar as etapas: `populateFromPost()` só preenche, `validate()` só valida e `populateFromGetAndValidate()` lê da query string, útil em formulários de busca. O capítulo [Dados tabulares](/formularios/dados-tabulares) usa o `populateFromPost()` e o `validate()` separados.

## Os tipos de campo

A classe `Field` tem um método para cada tipo de campo. Os mais usados:

- `text()`, `email()`, `password()`, `number()`, `url()`, `telephone()`, `date()`, `time()`, `dateTimeLocal()` e `hidden()` geram um `<input>` do tipo correspondente.
- `textarea()` gera um `<textarea>`.
- `select()` gera um `<select>`. As opções vêm do `optionsData()`.
- `checkbox()` gera um checkbox com um campo escondido para o valor desmarcado. `checkboxList()` e `radioList()` geram grupos.
- `file()` gera um campo de arquivo (veja [Upload de arquivos](/formularios/upload)).
- `submitButton()`, `resetButton()` e `button()` geram botões.
- `errorSummary()` lista todos os erros do formulário num bloco só.

Cada método devolve um objeto imutável. Você encadeia chamadas para ajustar o campo: `->label('Outro rótulo')`, `->hint('Outra dica')`, `->placeholder('...')`, `->hideLabel()`, `->inputClass('grande')`, `->containerClass('linha')` e assim por diante.

## Temas

Por padrão, cada campo é uma `<div>` com o rótulo, o input, a dica e os erros, sem classe nenhuma. Para mudar isso em todos os campos de uma vez, use um tema. Um tema é um conjunto de configurações registrado nos parâmetros do `yiisoft/form`. Crie `config/common/params/formularios.php`:

```php
<?php

declare(strict_types=1);

return [
    'yiisoft/form' => [
        'themes' => [
            'guia' => [
                'containerClass' => 'campo',
                'hintClass' => 'campo-dica',
                'errorClass' => 'campo-erro',
                'inputInvalidClass' => 'invalido',
            ],
        ],
    ],
];
```

As chaves do tema são os parâmetros do construtor de `Yiisoft\Form\Theme\Theme`. Além das classes, dá para trocar o `template` (a ordem de `{label}`, `{input}`, `{hint}` e `{error}`), a tag do container e configurações específicas por tipo de campo, em `fieldConfigs`.

O pacote já traz dois temas para Bootstrap 5, nos arquivos `vendor/yiisoft/form/config/theme-bootstrap5-vertical.php` e `theme-bootstrap5-horizontal.php`. Para usar um deles, registre o arquivo como um tema com `require` e, se quiser que valha para todos os formulários, aponte `'defaultTheme'` para ele.

Aqui não vamos mudar o tema padrão, para não alterar os outros formulários do app. Em vez disso, a view escolhe o tema. Crie `src/Web/Formularios/Contato/formulario.php`:

```php
<?php

declare(strict_types=1);

use App\Web\Formularios\Contato\ContatoForm;
use Yiisoft\FormModel\FieldFactory;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var ContatoForm $form
 */

$this->setTitle('Contato');

$this->registerCss(<<<CSS
    .campo { margin-bottom: 1rem; }
    .campo label { display: block; font-weight: bold; }
    .campo-dica { color: #666; font-size: .9em; }
    .campo-erro { color: #b00020; }
    .invalido { border-color: #b00020; }
    CSS);

$field = new FieldFactory('guia');
?>

<h1>Fale conosco</h1>

<?= Html::form()->post($urlGenerator->generate('formularios/contato'))->csrf($csrf)->open() ?>
    <?= $field->text($form, 'nome') ?>
    <?= $field->email($form, 'email') ?>
    <?= $field->select($form, 'assunto')->optionsData(ContatoForm::ASSUNTOS)->prompt('Selecione...') ?>
    <?= $field->textarea($form, 'mensagem')->rows(5) ?>
    <?= $field->checkbox($form, 'receberNovidades') ?>
    <?= $field->submitButton('Enviar') ?>
<?= Html::form()->close() ?>
```

O `FieldFactory` tem os mesmos métodos do `Field`, mas guarda um tema padrão. Com `new FieldFactory('guia')`, todos os campos criados por ele usam o tema `guia`. Outra opção é passar o tema campo a campo: `Field::text($form, 'nome', theme: 'guia')`.

O `registerCss()` adiciona um bloco `<style>` no `<head>` da página. Num projeto real, o CSS iria para um asset, mas aqui ele deixa o exemplo num arquivo só.

A página de confirmação, `src/Web/Formularios/Contato/enviado.php`:

```php
<?php

declare(strict_types=1);

use App\Web\Formularios\Contato\ContatoForm;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var ContatoForm $form
 */

$this->setTitle('Mensagem enviada');
?>

<h1>Recebemos a sua mensagem</h1>

<p>
    Obrigado, <?= Html::encode($form->nome) ?>.
    Assunto: <?= Html::encode(ContatoForm::ASSUNTOS[$form->assunto]) ?>.
    <?= $form->receberNovidades ? 'Você vai receber nossas novidades.' : 'Você não vai receber novidades.' ?>
</p>
```

## A rota

No arquivo de rotas, as rotas desta seção ficam num grupo com o prefixo `/formularios`:

```php
Group::create('/formularios')
    ->routes(
        Route::methods([Method::GET, Method::POST], '/contato')
            ->action(Web\Formularios\Contato\Action::class)
            ->name('formularios/contato'),
    ),
```

## Testando

Abra http://127.0.0.1:8080/formularios/contato. O campo de e-mail mostra a dica, e o nome mostra o placeholder. O HTML gerado para o e-mail fica assim:

```html
<div class="campo">
<label for="contatoform-email">E-mail</label>
<input type="email" name="ContatoForm[email]" value id="contatoform-email">
<div class="campo-dica">Usamos só para responder a esta mensagem.</div>
</div>
```

Enviando com o nome vazio, o e-mail `nao-e-email` e a mensagem `curta`, cada campo volta com o seu erro, e os inputs ganham a classe `invalido`. Enviando os dados certos com o checkbox marcado, aparece "Recebemos a sua mensagem" e a frase "Você vai receber nossas novidades.".

## Comparando com o Yii2

- O `ActiveForm` e o `$form->field($model, 'nome')` deram lugar ao `Field::text($form, 'nome')`. Não há a validação em JavaScript que o `ActiveForm` gerava: a validação acontece no servidor.
- `attributeLabels()` e `attributeHints()` viraram `getPropertyLabels()` e `getPropertyHints()`, e também podem ser atributos na propriedade.
- O `safe` do `rules()` virou o atributo `#[Safe]`. A regra continua a mesma: sem regra, o campo não é atribuído em massa.
- A aparência configurável do `ActiveField` (`fieldConfig`, `template`) é feita com temas.
