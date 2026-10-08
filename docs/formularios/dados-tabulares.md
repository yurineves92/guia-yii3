# Dados tabulares

Nem todo formulário é uma lista plana de campos. Um pedido tem um cliente, um endereço de entrega e vários itens, cada item com produto e quantidade. Este capítulo mostra como receber e validar isso num formulário só, em `/formularios/pedido`: o endereço como um formulário aninhado e os itens como uma lista de formulários.

## Os formulários menores

Cada parte com campos próprios vira um `FormModel`. O item, em `src/Web/Formularios/Pedido/ItemForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Rule\Integer;
use Yiisoft\Validator\Rule\Required;

final class ItemForm extends FormModel
{
    #[Required(message: 'Informe o produto.')]
    public string $produto = '';

    #[Required(message: 'Informe a quantidade.')]
    #[Integer(min: 1, max: 99, lessThanMinMessage: 'Mínimo de {min}.', greaterThanMaxMessage: 'Máximo de {max}.', skipOnEmpty: true)]
    public ?int $quantidade = null;

    public function estaVazio(): bool
    {
        return $this->produto === '' && $this->quantidade === null;
    }
}
```

E o endereço, em `src/Web/Formularios/Pedido/EnderecoForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Validator\Label;
use Yiisoft\Validator\Rule\Regex;
use Yiisoft\Validator\Rule\Required;

final class EnderecoForm extends FormModel
{
    #[Label('CEP')]
    #[Required(message: 'Informe o CEP.')]
    #[Regex('/^\d{5}-?\d{3}$/', message: 'Use o formato 00000-000.', skipOnEmpty: true)]
    public string $cep = '';

    #[Label('Cidade')]
    #[Required(message: 'Informe a cidade.')]
    public string $cidade = '';
}
```

## O formulário principal

O pedido junta as partes. Crie `src/Web/Formularios/Pedido/PedidoForm.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

use Yiisoft\FormModel\FormModel;
use Yiisoft\Hydrator\Attribute\Parameter\Collection;
use Yiisoft\Validator\Rule\Count;
use Yiisoft\Validator\Rule\Each;
use Yiisoft\Validator\Rule\Nested;
use Yiisoft\Validator\Rule\Required;

final class PedidoForm extends FormModel
{
    #[Required(message: 'Informe o nome do cliente.')]
    public string $cliente = '';

    #[Nested]
    public EnderecoForm $endereco;

    /** @var ItemForm[] */
    #[Collection(ItemForm::class)]
    #[Count(min: 1, lessThanMinMessage: 'Adicione pelo menos um item.')]
    #[Each(new Nested())]
    public array $itens = [];

    public function __construct(int $linhas = 3)
    {
        $this->endereco = new EnderecoForm();

        for ($i = 0; $i < $linhas; $i++) {
            $this->itens[] = new ItemForm();
        }
    }

    /**
     * Descarta as linhas que o usuário deixou totalmente em branco.
     */
    public function removerItensVazios(): void
    {
        $this->itens = array_values(array_filter(
            $this->itens,
            static fn(ItemForm $item): bool => !$item->estaVazio(),
        ));
    }
}
```

Duas coisas acontecem com cada propriedade composta: o preenchimento e a validação.

### Formulário aninhado

A propriedade `$endereco` é tipada como `EnderecoForm`. Quando o POST traz `PedidoForm[endereco][cep]` e `PedidoForm[endereco][cidade]`, o hydrator recebe um array para essa propriedade, vê que o tipo é uma classe e cria um `EnderecoForm` preenchido com ele.

A regra `#[Nested]` faz o validador entrar no objeto e aplicar as regras declaradas nele. Sem argumentos, ela usa os atributos da própria classe `EnderecoForm`. Os erros voltam com o caminho completo: o erro do CEP fica em `endereco.cep`.

### Lista de formulários

A propriedade `$itens` é um array, e o tipo `array` não diz ao hydrator o que existe dentro dele. Quem diz é o atributo `#[Collection(ItemForm::class)]`, do pacote `yiisoft/hydrator`: cada elemento do array recebido vira um `ItemForm`.

Para validar, duas regras:

- `#[Count(min: 1)]` confere o tamanho da lista.
- `#[Each(new Nested())]` aplica o `Nested` em cada elemento, ou seja, valida cada `ItemForm` com as regras dele. Os erros ficam em caminhos como `itens.0.quantidade`.

O construtor cria três itens vazios, para a página começar com três linhas. Ele não roda de novo no preenchimento: o `FormHydrator` preenche o objeto que você criou.

## A action

Aqui o preenchimento e a validação são separados, porque entre um e outro descartamos as linhas em branco. Crie `src/Web/Formularios/Pedido/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Formularios\Pedido;

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
        $form = new PedidoForm();

        if ($this->formHydrator->populateFromPost($form, $request)) {
            $form->removerItensVazios();

            if ($this->formHydrator->validate($form)->isValid()) {
                return $this->viewRenderer->render(__DIR__ . '/resumo', ['form' => $form]);
            }
        }

        return $this->viewRenderer->render(__DIR__ . '/formulario', ['form' => $form]);
    }
}
```

O `populateFromPost()` devolve `true` se havia dados do formulário no POST. O `validate()` devolve o `Result` e também guarda o resultado no formulário, então a view consegue mostrar os erros do mesmo jeito que nos outros capítulos.

## A view

Para campos dentro de objetos e arrays, o segundo argumento do `Field` aceita um caminho com colchetes: `endereco[cep]` ou `itens[0][produto]`. O nome do campo no HTML fica `PedidoForm[endereco][cep]` e `PedidoForm[itens][0][produto]`, que é exatamente a estrutura que o hydrator espera. Crie `src/Web/Formularios/Pedido/formulario.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Formularios\Pedido\PedidoForm $form
 */

$this->setTitle('Novo pedido');

$linhas = max(3, count($form->itens));
$erros = $form->isValidated() ? $form->getValidationResult()->getErrorMessagesIndexedByPath() : [];
?>

<h1>Novo pedido</h1>

<?= Html::form()->post($urlGenerator->generate('formularios/pedido'))->csrf($csrf)->open() ?>
    <?= Field::text($form, 'cliente') ?>

    <fieldset>
        <legend>Endereço de entrega</legend>
        <?= Field::text($form, 'endereco[cep]') ?>
        <?= Field::text($form, 'endereco[cidade]') ?>
    </fieldset>

    <table>
        <tr>
            <th>Produto</th>
            <th>Quantidade</th>
        </tr>
        <?php for ($i = 0; $i < $linhas; $i++): ?>
            <tr>
                <td>
                    <?= Field::text($form, "itens[$i][produto]")
                        ->hideLabel()
                        ->error($erros["itens.$i.produto"][0] ?? null) ?>
                </td>
                <td>
                    <?= Field::number($form, "itens[$i][quantidade]")
                        ->hideLabel()
                        ->error($erros["itens.$i.quantidade"][0] ?? null) ?>
                </td>
            </tr>
        <?php endfor ?>
    </table>
    <?= isset($erros['itens']) ? Html::div($erros['itens'][0]) : '' ?>

    <?= Field::submitButton('Fechar pedido') ?>
<?= Html::form()->close() ?>
```

Os campos do endereço funcionam sem nada especial: o rótulo vem do `#[Label]` do `EnderecoForm`, e os erros aparecem embaixo de cada campo.

Nos itens, a view passa os erros com `->error()`, e o motivo é uma limitação da versão 1.1 do `yiisoft/form-model`. O campo procura os seus erros comparando o caminho dele, `['itens', '0', 'produto']`, com o caminho do erro, `['itens', 0, 'produto']`. Como a comparação é estrita e o índice de um vem como texto e o do outro como inteiro, o campo não encontra o erro sozinho. O `getErrorMessagesIndexedByPath()` devolve os erros indexados por caminhos como `itens.0.produto`, e o `->error()` coloca a mensagem no campo. Quando não há erro, o `?? null` mantém o comportamento padrão.

Pelo mesmo motivo, o erro do `Count` é mostrado à parte: `Field::error($form, 'itens')` mostraria também os erros de todas as linhas, porque eles começam com o caminho `itens`.

Com `$linhas = max(3, count($form->itens))`, a página sempre mostra pelo menos três linhas. Depois de um envio com erros, as linhas preenchidas aparecem primeiro, reindexadas, e as que faltam para completar três aparecem vazias. Como o `getPropertyValue()` do formulário devolve `null` para um índice que não existe, essas linhas extras não precisam de um `ItemForm` no array.

A página de resumo, em `src/Web/Formularios/Pedido/resumo.php`:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var App\Web\Formularios\Pedido\PedidoForm $form
 */

$this->setTitle('Pedido recebido');
?>

<h1>Pedido de <?= Html::encode($form->cliente) ?></h1>

<p>Entrega em <?= Html::encode($form->endereco->cidade) ?>, CEP <?= Html::encode($form->endereco->cep) ?>.</p>

<ul>
    <?php foreach ($form->itens as $item): ?>
        <li><?= $item->quantidade ?> x <?= Html::encode($item->produto) ?></li>
    <?php endforeach ?>
</ul>
```

E a rota, no grupo `/formularios`:

```php
Route::methods([Method::GET, Method::POST], '/pedido')
    ->action(Web\Formularios\Pedido\Action::class)
    ->name('formularios/pedido'),
```

## Testando

Abra http://127.0.0.1:8080/formularios/pedido.

- Enviando tudo em branco: "Informe o nome do cliente.", "Informe o CEP.", "Informe a cidade." e "Adicione pelo menos um item.".
- Com o CEP `123`: "Use o formato 00000-000.".
- Com a primeira linha `Caneta` / `0`, a segunda em branco e a terceira só com a quantidade `5`: a linha em branco some, a primeira mostra "Mínimo de 1." e a antiga terceira, agora na segunda posição, mostra "Informe o produto.".
- Com cliente, endereço válido e as linhas `Caneta` / `10` e `Caderno` / `2`: o resumo lista "10 x Caneta" e "2 x Caderno".

## Vários formulários independentes

Outra forma de editar vários registros é ter um `FormModel` por registro, sem um formulário que os agrupe. O `Field` aceita um prefixo entre colchetes antes do nome da propriedade para isso:

```php
Field::text($item, '[1]produto')
// <input type="text" name="ItemForm[1][produto]" ...>
```

Na action, você percorre o array do POST e preenche cada formulário com o seu pedaço. O `scope: ''` diz ao `FormHydrator` que os dados já estão no nível certo, sem o nome do formulário em volta:

```php
foreach ($request->getParsedBody()['ItemForm'] ?? [] as $i => $dados) {
    $itens[$i] = new ItemForm();
    $this->formHydrator->populateAndValidate($itens[$i], $dados, scope: '');
}
```

Essa forma serve, por exemplo, para editar uma página de registros vindos do banco, cada um com o seu id. Quando os registros pertencem a um todo, como os itens de um pedido, o formulário único com `Each` e `Nested` deixa a validação num lugar só.

## Comparando com o Yii2

- O `Model::loadMultiple()` e o `Model::validateMultiple()` não existem. A lista de registros é uma propriedade com `#[Collection]` e `#[Each(new Nested())]`, ou um laço sobre vários formulários.
- A sintaxe `[$i]campo` para campos tabulares continua igual.
- Um formulário pode conter outro formulário como propriedade, com a validação descendo pelo `Nested`. No Yii2, isso exigia código manual.
