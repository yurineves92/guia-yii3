# Validação

Todo dado que vem do usuário precisa ser conferido antes de ser usado. No Yii3, quem faz isso é o pacote `yiisoft/validator`, que o `yiisoft/form-model` já instala. Este capítulo mostra como declarar regras, quais regras vêm prontas, como fazer regras que dependem de outros campos e como escrever uma regra sua. O exemplo é uma inscrição num evento, em `/formularios/inscricao`.

## Regras como atributos

Cada regra é uma classe em `Yiisoft\Validator\Rule`, usada como atributo PHP em cima da propriedade. Uma propriedade pode ter várias regras, e elas rodam na ordem em que aparecem. Todas aceitam alguns parâmetros em comum:

- `message` (ou mensagens mais específicas, como `lessThanMinMessage`): o texto do erro. Dentro dele, `{Property}` vira o rótulo da propriedade, e parâmetros da regra, como `{min}` e `{max}`, viram os valores configurados.
- `skipOnEmpty: true`: pula a regra quando o valor está vazio. É o que evita dois erros num campo vazio que tem `Required` e `Email`.
- `skipOnError: true`: pula a regra se uma regra anterior da mesma propriedade já falhou.
- `when`: uma função que decide se a regra roda. Como os atributos do PHP 8.4 não aceitam funções anônimas como argumento, o `when` é usado nas regras declaradas por código, mostradas mais adiante.

Crie `src/Web/Formularios/Inscricao/InscricaoForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Inscricao;

use App\Formularios\Validacao\Cpf;
use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\CompareType;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Equal;
use Yiisoft\Validator\Rule\In;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Length;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\Url;
use Yiisoft\Validator\RulesProviderInterface;
use Yiisoft\Validator\ValidationContext;

final class InscricaoForm extends FormModel implements RulesProviderInterface
{
    public const TIPOS = [
        'estudante' => 'Estudante',
        'profissional' => 'Profissional',
    ];

    #[Required(message: 'Informe o nome.')]
    #[Length(max: 80, greaterThanMaxMessage: 'Use no máximo {max} caracteres.')]
    public string $nome = '';

    #[Label('CPF')]
    #[Required(message: 'Informe o CPF.')]
    #[Cpf(skipOnEmpty: true)]
    public string $cpf = '';

    #[Label('E-mail')]
    #[Required(message: 'Informe o e-mail.')]
    #[Email(message: 'Este e-mail não parece válido.', skipOnEmpty: true)]
    public string $email = '';

    #[Label('Repita o e-mail')]
    #[Equal(targetProperty: 'email', type: CompareType::STRING, message: 'Os e-mails não conferem.')]
    public string $confirmacaoEmail = '';

    #[Required(message: 'Informe a idade.')]
    #[Integer(
        min: 16,
        max: 120,
        lessThanMinMessage: 'A idade mínima é {min} anos.',
        greaterThanMaxMessage: 'Confira a idade informada.',
        skipOnEmpty: true,
    )]
    public ?int $idade = null;

    #[Label('Tipo de ingresso')]
    #[Required(message: 'Escolha o tipo de ingresso.')]
    #[In(['estudante', 'profissional'], message: 'Tipo de ingresso inválido.', skipOnEmpty: true)]
    public string $tipo = '';

    #[Label('Instituição de ensino')]
    public string $instituicao = '';

    #[Label('Site ou portfólio')]
    #[Url(message: 'Informe um endereço começando com http:// ou https://.', skipOnEmpty: true)]
    public string $site = '';

    public function getRules(): array
    {
        return [
            'instituicao' => [
                new Required(
                    message: 'Estudantes precisam informar a instituição.',
                    when: static fn(mixed $value, ValidationContext $context): bool
                        => $context->getDataSet()->getPropertyValue('tipo') === 'estudante',
                ),
            ],
        ];
    }
}
```

Alguns pontos:

- O `Equal` com `targetProperty` compara com outra propriedade do mesmo formulário. O `type: CompareType::STRING` é necessário porque o padrão compara como número.
- O `Cpf` não é do pacote: é a regra própria que vamos escrever no fim do capítulo.
- A idade é `?int`. O `FormHydrator` converte o texto do POST para o tipo da propriedade antes de validar: um campo vazio vira `null` (e o `Required` reclama), e um texto como `abc` vira `0`, que cai no "A idade mínima é 16 anos.". Se você quer tratar texto não numérico com uma mensagem própria, declare a propriedade como `string` e deixe o `Integer` fazer a conferência.

## Regras nativas

As regras mais comuns do `yiisoft/validator`:

- Presença e texto: `Required`, `Length` (tamanho de string), `Regex`, `StringValue`.
- Formatos: `Email`, `Url`, `Ip`, `Uuid`, `Json`.
- Números: `Number`, `Integer`, `GreaterThan`, `GreaterThanOrEqual`, `LessThan`, `LessThanOrEqual`.
- Comparação: `Equal`, `NotEqual`, `Compare` (com o operador que você escolher). Todas aceitam `targetValue` ou `targetProperty`.
- Listas de valores: `In`, `InEnum` (casos de um enum), `Subset`.
- Booleanos: `BooleanValue` e `TrueValue` (útil para "aceito os termos").
- Arquivos: `File` e `Image\Image` (veja [Upload de arquivos](/formularios/upload)).
- Datas: as regras em `Yiisoft\Validator\Rule\Date`.
- Estrutura: `Nested`, `Each` e `Count`, para objetos e arrays (veja [Dados tabulares](/formularios/dados-tabulares)).
- Composição: `Callback` (chama um método do próprio formulário, com `#[Callback(method: 'nomeDoMetodo')]`), `Composite`, `AnyRule`, `StopOnError`, `FilledAtLeast` e `FilledOnlyOneOf`.

## Regras declaradas por código

Quando uma regra depende de outro campo, ou precisa de algo que não cabe num atributo, o formulário implementa `RulesProviderInterface` e devolve as regras em `getRules()`. As regras dos atributos continuam valendo: o validador junta as duas fontes.

No exemplo, a instituição de ensino só é obrigatória para estudantes. O `when` recebe o valor e o `ValidationContext`. Pelo contexto, `getDataSet()->getPropertyValue('tipo')` lê o valor de outra propriedade. Se a função devolve `false`, a regra não roda.

Propriedades que aparecem em `getRules()` também contam como "tem regra" para o `FormHydrator`, então `instituicao` é preenchida a partir do POST mesmo sem atributo.

## A view

A action é igual à do [formulário de contato](/formularios/criando-formularios), trocando a classe do formulário e o nome da view de confirmação (`confirmada`). A view do formulário, em `src/Web/Formularios/Inscricao/formulario.php`:

```php
<?php

declare(strict_types=1);

use App\Web\Formularios\Inscricao\InscricaoForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var InscricaoForm $form
 */

$this->setTitle('Inscrição');
?>

<h1>Inscrição no evento</h1>

<?= Html::form()->post($urlGenerator->generate('formularios/inscricao'))->csrf($csrf)->open() ?>
    <?= Field::text($form, 'nome') ?>
    <?= Field::text($form, 'cpf') ?>
    <?= Field::email($form, 'email') ?>
    <?= Field::email($form, 'confirmacaoEmail') ?>
    <?= Field::number($form, 'idade') ?>
    <?= Field::radioList($form, 'tipo')->items(InscricaoForm::TIPOS) ?>
    <?= Field::text($form, 'instituicao') ?>
    <?= Field::url($form, 'site') ?>
    <?= Field::submitButton('Inscrever') ?>
<?= Html::form()->close() ?>
```

E a rota, no grupo `/formularios`:

```php
Route::methods([Method::GET, Method::POST], '/inscricao')
    ->action(Web\Formularios\Inscricao\Action::class)
    ->name('formularios/inscricao'),
```

## Escrevendo uma regra própria

Uma regra tem duas partes: a classe da regra, que guarda a configuração e é usada como atributo, e o handler, que faz a validação. A regra implementa `RuleInterface`, cujo único método obrigatório é `getHandler()`.

Crie `src/Formularios/Validacao/Cpf.php`:

```php
<?php

declare(strict_types=1);

namespace App\Formularios\Validacao;

use Attribute;
use Yiisoft\Validator\Rule\Trait\SkipOnEmptyTrait;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\SkipOnEmptyInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Cpf implements RuleInterface, SkipOnEmptyInterface
{
    use SkipOnEmptyTrait;

    public function __construct(
        public readonly string $message = 'O CPF informado não é válido.',
        bool|callable|null $skipOnEmpty = null,
    ) {
        $this->skipOnEmpty = $skipOnEmpty;
    }

    public function getHandler(): string
    {
        return CpfHandler::class;
    }
}
```

O `#[Attribute]` permite usar a classe como atributo. A interface `SkipOnEmptyInterface` com o `SkipOnEmptyTrait` dá à regra o parâmetro `skipOnEmpty`, igual ao das regras nativas. Para ter `skipOnError` e `when`, existem do mesmo jeito `SkipOnErrorInterface`/`SkipOnErrorTrait` e `WhenInterface`/`WhenTrait`.

E o handler, em `src/Formularios/Validacao/CpfHandler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Formularios\Validacao;

use Yiisoft\Validator\Exception\UnexpectedRuleException;
use Yiisoft\Validator\Result;
use Yiisoft\Validator\RuleHandlerInterface;
use Yiisoft\Validator\RuleInterface;
use Yiisoft\Validator\ValidationContext;

final class CpfHandler implements RuleHandlerInterface
{
    public function validate(mixed $value, RuleInterface $rule, ValidationContext $context): Result
    {
        if (!$rule instanceof Cpf) {
            throw new UnexpectedRuleException(Cpf::class, $rule);
        }

        $result = new Result();

        if (!is_string($value) || !$this->cpfValido($value)) {
            $result->addError($rule->message, ['property' => $context->getTranslatedProperty()]);
        }

        return $result;
    }

    private function cpfValido(string $cpf): bool
    {
        $digitos = preg_replace('/\D/', '', $cpf);

        // 11 dígitos, e não todos iguais (111.111.111-11 passa no cálculo, mas não é válido)
        if (strlen($digitos) !== 11 || preg_match('/^(\d)\1{10}$/', $digitos) === 1) {
            return false;
        }

        // Os dois últimos dígitos são verificadores, calculados a partir dos anteriores
        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * ($posicao + 1 - $i);
            }
            $verificador = ($soma * 10) % 11 % 10;
            if ((int) $digitos[$posicao] !== $verificador) {
                return false;
            }
        }

        return true;
    }
}
```

O handler devolve um `Result`. Um `Result` sem erros significa que o valor passou. O segundo argumento de `addError()` são os parâmetros que podem aparecer na mensagem, como `{property}`.

O validador encontra o handler pelo nome de classe que `getHandler()` devolve e cria a instância com `new`. Se o seu handler precisa de dependências (uma conexão de banco para checar se um e-mail já existe, por exemplo), troque a definição de `Yiisoft\Validator\RuleHandlerResolverInterface` no container por `Yiisoft\Validator\RuleHandlerResolver\RuleHandlerContainer`, que cria os handlers pelo [container de DI](/conceitos/container-di).

## Testando

Abra http://127.0.0.1:8080/formularios/inscricao e envie:

- CPF `123.456.789-00`, e-mails diferentes nos dois campos, idade `abc`, tipo "Estudante" sem instituição e site `meusite`. Voltam os erros "O CPF informado não é válido.", "Os e-mails não conferem.", "A idade mínima é 16 anos.", "Estudantes precisam informar a instituição." e "Informe um endereço começando com http:// ou https://.".
- CPF `529.982.247-25`, os dois e-mails iguais, idade `30` e tipo "Profissional", deixando a instituição vazia. Aparece "Inscrição confirmada", porque a instituição só é exigida de estudantes.

## Validando fora de formulários

O validador não depende de `FormModel`. Peça `Yiisoft\Validator\ValidatorInterface` no construtor e valide qualquer array ou objeto:

```php
$result = $this->validator->validate(
    ['email' => 'nao-e-email', 'cpf' => '123'],
    [
        'email' => [new Required(), new Email()],
        'cpf' => [new Cpf()],
    ],
);

$result->isValid(); // false
$result->getErrorMessagesIndexedByPath();
// ['email' => ['Email is not a valid email address.'], 'cpf' => ['O CPF informado não é válido.']]
```

Passando um objeto sem o segundo argumento, as regras são lidas dos atributos dele, como acontece com o formulário.

## Mensagens padrão em português

As mensagens padrão aparecem em inglês porque o tradutor do app está configurado com o idioma `en-US`. O `yiisoft/validator` já traz as traduções para `pt-BR`. Com o parâmetro do tradutor em português, nos parâmetros do app:

```php
'yiisoft/translator' => [
    'locale' => 'pt-BR',
],
```

as mensagens padrão passam a sair como "Nome não pode estar vazio." e "Senha deve conter pelo menos 8 caracteres.". Isso muda o idioma do app inteiro, e não só do validador. O capítulo [Internacionalização](/topicos/i18n) trata do tradutor.

## Comparando com o Yii2

- O método `rules()` com arrays como `[['nome', 'email'], 'required']` virou um atributo por regra, em cima de cada propriedade. Para regras dinâmicas, `getRules()` cumpre o papel do antigo `rules()`.
- Os validadores inline e o `when` continuam existindo, como `Callback` e o parâmetro `when`.
- Não existem cenários. Formulários diferentes para situações diferentes (criar e editar, por exemplo) são classes diferentes, ou usam `when`.
- Uma regra própria deixou de ser uma subclasse de `Validator` e virou um par regra + handler.
