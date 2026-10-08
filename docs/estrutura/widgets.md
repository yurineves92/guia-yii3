# Widgets

Um widget é um pedaço de HTML reutilizável com lógica própria: um alerta, um menu, um cartão, uma tabela paginada. Em vez de copiar o mesmo trecho de view em várias páginas, você escreve uma classe e a chama de qualquer view. No Yii3, os widgets são do pacote `yiisoft/widget`, e este capítulo mostra como escrever os seus, inclusive widgets que recebem serviços do container.

## yiisoft/widget

O pacote é pequeno. A peça central é a classe abstrata `Yiisoft\Widget\Widget`, que exige um método:

```php
abstract public function render(): string;
```

E oferece:

- `Widget::widget()`: método estático que cria a instância. É sempre por ele que você cria um widget, e não com `new`.
- `__toString()`: chama `render()`. Por isso `<?= Alerta::widget() ?>` funciona numa view.
- `begin()` e `end()`: para widgets que envolvem um trecho de conteúdo.

O `widget()` não chama o construtor direto. Ele passa por `WidgetFactory`, que usa o container para resolver as dependências do construtor. A fábrica é inicializada por um callback de bootstrap que o próprio pacote registra na configuração (`vendor/yiisoft/widget/config/bootstrap.php`), então ela já está pronta em qualquer view.

Os widgets do Yii3 seguem o padrão imutável: cada método de configuração devolve uma cópia com o valor alterado. Você encadeia as chamadas: `Alerta::widget()->tipo('sucesso')->mensagem('...')`.

Vários pacotes trazem widgets prontos: o `yiisoft/form` tem os campos de formulário e o `yiisoft/yii-dataview` tem `GridView` e `ListView` (veja [Widgets de dados](/dados/widgets-de-dados)).

## Criando um widget próprio

Um alerta com tipo e mensagem, em `src/Web/Estrutura/Widget/Alerta.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Widget\Widget;

final class Alerta extends Widget
{
    private string $tipo = 'info';
    private string $mensagem = '';

    public function tipo(string $tipo): self
    {
        $new = clone $this;
        $new->tipo = $tipo;
        return $new;
    }

    public function mensagem(string $mensagem): self
    {
        $new = clone $this;
        $new->mensagem = $mensagem;
        return $new;
    }

    public function render(): string
    {
        if ($this->mensagem === '') {
            return '';
        }

        return Html::div($this->mensagem)
            ->class('alerta', 'alerta-' . $this->tipo)
            ->render();
    }
}
```

O `Html::div()` do `yiisoft/html` codifica o conteúdo, então uma mensagem com `<` ou `&` não quebra a página nem abre brecha para XSS. Sem mensagem, o widget não gera nada.

### Widgets que envolvem conteúdo

Alguns widgets envolvem um trecho de HTML escrito na própria view. Para isso existem `begin()` e `end()`. Um cartão com título, em `src/Web/Estrutura/Widget/Cartao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Widget\Widget;

final class Cartao extends Widget
{
    private string $titulo = '';

    public function titulo(string $titulo): self
    {
        $new = clone $this;
        $new->titulo = $titulo;
        return $new;
    }

    public function begin(): ?string
    {
        parent::begin();
        ob_start();

        return null;
    }

    public function render(): string
    {
        $conteudo = (string) ob_get_clean();

        return Html::div(Html::h2($this->titulo) . $conteudo)
            ->class('cartao')
            ->encode(false)
            ->render();
    }
}
```

O `begin()` da classe base empilha o widget; aqui ele também abre um buffer de saída, que captura tudo o que a view imprimir até o `end()`. O `Cartao::end()` (estático) desempilha o widget e chama `render()`, que fecha o buffer e monta o HTML. O `encode(false)` é necessário porque o conteúdo já é HTML; o título continua codificado pelo `Html::h2()`.

Este widget só faz sentido com `begin()`/`end()`. Chamado com `<?= Cartao::widget() ?>`, o `ob_get_clean()` fecharia o buffer que a própria `WebView` abre para renderizar a view, e a página sairia quebrada.

## Widgets com configuração por DI

Como o `widget()` passa pelo container, o construtor de um widget pode pedir serviços. Um menu que marca o link da página atual precisa do gerador de URLs e da rota atual. Em `src/Web/Estrutura/Widget/MenuSecao.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Widget\Widget;

final class MenuSecao extends Widget
{
    /**
     * @var array<string, string> Rótulo => nome da rota.
     */
    private array $itens = [];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CurrentRoute $currentRoute,
    ) {}

    /**
     * @param array<string, string> $itens Rótulo => nome da rota.
     */
    public function itens(array $itens): self
    {
        $new = clone $this;
        $new->itens = $itens;
        return $new;
    }

    public function render(): string
    {
        $links = [];

        foreach ($this->itens as $rotulo => $rota) {
            $links[] = Html::a($rotulo, $this->urlGenerator->generate($rota))
                ->addClass($rota === $this->currentRoute->getName() ? 'ativo' : null)
                ->render();
        }

        return Html::nav(implode(' ', $links))->encode(false)->render();
    }
}
```

Na view, ninguém passa o `UrlGeneratorInterface` nem o `CurrentRoute`: `MenuSecao::widget()` já devolve o widget com as dependências resolvidas.

### Usando os três widgets

Uma página para os widgets, com a action em `src/Web/Estrutura/Widgets/Action.php` (igual à da página inicial do template, só renderiza a view) e a view em `src/Web/Estrutura/Widgets/template.php`:

```php
<?php

declare(strict_types=1);

use App\Web\Estrutura\Widget\Alerta;
use App\Web\Estrutura\Widget\Cartao;
use App\Web\Estrutura\Widget\MenuSecao;

/**
 * @var Yiisoft\View\WebView $this
 */

$this->setTitle('Widgets');
?>

<?= MenuSecao::widget()->itens([
    'Widgets' => 'estrutura/widgets',
    'Views' => 'estrutura/views',
    'Tarefas' => 'estrutura/tarefas',
]) ?>

<h1>Widgets</h1>

<?= Alerta::widget()->tipo('sucesso')->mensagem('Tudo certo por aqui.') ?>
<?= Alerta::widget(config: ['tipo()' => ['erro']])->mensagem('Algo deu <errado>.') ?>
<?= Alerta::widget() ?>

<?php Cartao::widget()->titulo('Um cartão')->begin() ?>
    <p>Este conteúdo fica entre o <code>begin()</code> e o <code>end()</code>.</p>
<?= Cartao::end() ?>
```

A rota:

```php
Route::get('/estrutura/widgets')
    ->action(Estrutura\Widgets\Action::class)
    ->name('estrutura/widgets'),
```

O conteúdo gerado em `/estrutura/widgets`, dentro do layout do template:

```plaintext
<nav><a href="/estrutura/widgets" class="ativo">Widgets</a> <a href="/estrutura/views">Views</a> <a href="/estrutura/tarefas">Tarefas</a></nav>
<h1>Widgets</h1>
<div class="alerta alerta-sucesso">Tudo certo por aqui.</div><div class="alerta alerta-erro">Algo deu &lt;errado&gt;.</div>
<div class="cartao"><h2>Um cartão</h2>    <p>Este conteúdo fica entre o <code>begin()</code> e o <code>end()</code>.</p>
</div>
```

Repare em três detalhes:

- O link "Widgets" ganhou a classe `ativo`, porque a rota atual se chama `estrutura/widgets`.
- O `<errado>` foi codificado.
- O terceiro `Alerta::widget()`, sem mensagem, não gerou nada.

### Os argumentos de widget()

A assinatura completa é `widget(array $constructorArguments = [], array $config = [], ?string $theme = null)`:

- `$constructorArguments` passa argumentos do construtor por nome, para os que o container não consegue resolver, como strings e números.
- `$config` é uma definição no formato do `yiisoft/definitions`: `'metodo()' => [argumentos]` chama um método, `'$propriedade' => valor` define uma propriedade pública. O segundo alerta acima usa `['tipo()' => ['erro']]`, que tem o mesmo efeito de `->tipo('erro')`. Num widget imutável como o `Alerta`, o método devolve uma cópia, e é a cópia que vale.
- `$theme` escolhe um tema, explicado a seguir.

### Padrões de configuração e temas

O `WidgetFactory` aceita uma lista de definições padrão por classe e uma lista de temas. Elas vêm de dois grupos de configuração, `widgets` e `widgets-themes`, lidos pelo bootstrap do pacote. O efeito é este (o trecho abaixo chama a fábrica direto, num script isolado, só para mostrar o comportamento):

```php
WidgetFactory::initialize(
    container: new Container(ContainerConfig::create()),
    definitions: [
        Alerta::class => [
            'tipo()' => ['aviso'],
        ],
    ],
    themes: ['escuro' => [Alerta::class => ['tipo()' => ['escuro']]]],
);
echo Alerta::widget()->mensagem('a'), "\n";
echo Alerta::widget()->tipo('erro')->mensagem('b'), "\n";
echo Alerta::widget(theme: 'escuro')->mensagem('c'), "\n";
```

```plaintext
<div class="alerta alerta-aviso">a</div>
<div class="alerta alerta-erro">b</div>
<div class="alerta alerta-escuro">c</div>
```

A definição padrão vira o ponto de partida de todo `Alerta::widget()`, e o que a view encadeia depois tem prioridade. O tema se aplica por cima da definição padrão. Os parâmetros `defaultTheme` e `widgetDefaultThemes` de `yiisoft/widget` escolhem um tema para todos os widgets ou para uma classe.

::: warning
O `config/configuration.php` do template não declara os grupos `widgets` e `widgets-themes` para a aplicação, então o app de exemplo não usa esse recurso. Para usá-lo, você declara o grupo nesse arquivo, aponta para um arquivo seu e roda `composer yii-config-rebuild`. Veja [Configuração](/conceitos/configuracao).
:::

Para a maioria dos casos, o que você precisa é o que o `MenuSecao` faz: pedir serviços no construtor. As definições padrão servem para ajustar widgets de pacotes, como mudar as classes CSS de todos os campos de formulário.

## Comparando com o Yii2

- No Yii2, o widget estendia `yii\base\Widget`, era configurado com um array de propriedades (`Alerta::widget(['tipo' => 'sucesso'])`) e implementava `run()`. No Yii3, o método é `render()` e a configuração é feita com métodos encadeados.
- O `begin()`/`end()` continua, com a diferença de que `begin()` é chamado na instância (`Cartao::widget()->begin()`) e `end()` é estático.
- No Yii2, um widget pegava serviços em `Yii::$app`. No Yii3, ele os recebe pelo construtor.
- O `Yii::$container->set()` usado para mudar os padrões de um widget deu lugar ao grupo de configuração `widgets` e aos temas.
