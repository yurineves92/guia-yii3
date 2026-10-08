# Formatação

O banco devolve `34.9` e `2026-02-02 10:00:00`. Na tela, o usuário espera `R$ 34,90` e `02/02/2026, 10:00`. Este capítulo mostra como fazer essa conversão com a extensão `intl` do PHP e como deixar a formatação disponível nas views.

O Yii3 não tem um componente de formatação como o `Yii::$app->formatter` do Yii2, e o template não traz nenhum pacote para isso. A extensão `intl` resolve: ela usa a biblioteca ICU, que conhece as regras de cada idioma e país. Confira se ela está ativa com `php -m`.

## Números e moeda

A classe `NumberFormatter` recebe o locale e um estilo:

```php
$moeda = new NumberFormatter('pt_BR', NumberFormatter::CURRENCY);
$moeda->formatCurrency(1234567.891, 'BRL');   // R$ 1.234.567,89
$moeda->formatCurrency(34.9, 'USD');          // US$ 34,90

(new NumberFormatter('en_US', NumberFormatter::CURRENCY))->formatCurrency(1234567.891, 'BRL');
// R$1,234,567.89

(new NumberFormatter('pt_BR', NumberFormatter::DECIMAL))->format(1440000000);   // 1.440.000.000
(new NumberFormatter('pt_BR', NumberFormatter::PERCENT))->format(0.125);        // 12%
```

O locale define os separadores e a posição do símbolo; o código da moeda (`BRL`, `USD`, `EUR`) define qual símbolo usar. Preço em reais mostrado para um usuário americano continua sendo em reais, só muda a pontuação.

As casas decimais são controladas com `setAttribute()`:

```php
$numero = new NumberFormatter('pt_BR', NumberFormatter::DECIMAL);
$numero->setAttribute(NumberFormatter::FRACTION_DIGITS, 1);
$numero->format(68.2333);   // 68,2
```

::: warning
Entre `R$` e o número, o ICU coloca um espaço não separável (U+00A0), e não um espaço comum. Na tela não faz diferença, mas um teste que compara com `'R$ 34,90'` digitado no teclado falha.
:::

## Datas

Para datas, use `IntlDateFormatter`, com um estilo para a data e outro para a hora:

```php
$data = new DateTimeImmutable('2026-02-02 10:00:00');

(new IntlDateFormatter('pt_BR', IntlDateFormatter::SHORT, IntlDateFormatter::NONE))->format($data);
// 02/02/2026
(new IntlDateFormatter('pt_BR', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE))->format($data);
// 2 de fev. de 2026
(new IntlDateFormatter('pt_BR', IntlDateFormatter::LONG, IntlDateFormatter::NONE))->format($data);
// 2 de fevereiro de 2026
(new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE))->format($data);
// segunda-feira, 2 de fevereiro de 2026
(new IntlDateFormatter('pt_BR', IntlDateFormatter::SHORT, IntlDateFormatter::SHORT))->format($data);
// 02/02/2026, 10:00
```

Se nenhum estilo serve, passe um padrão do ICU no sexto argumento. Ele não é o mesmo formato do `date()` do PHP: `MMMM` é o nome do mês e o texto fixo vai entre aspas simples.

```php
(new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, "d 'de' MMMM 'de' y, HH:mm"))
    ->format($data);
// 2 de fevereiro de 2026, 10:00
```

O quarto argumento é o fuso horário. Com `null`, vale o fuso padrão do PHP (`date.timezone`). Passando outro, a hora é convertida: com `'UTC'`, a mesma data acima sai como `02/02/2026, 13:00`, porque o fuso padrão desta máquina é `America/Sao_Paulo`. Se o banco guarda datas em UTC e você quer exibir no horário do usuário, é aqui que a conversão acontece.

## Um serviço de formatação

Criar os formatadores em toda view repete o locale e a moeda em vários lugares. Uma classe pequena resolve. Crie `src/Dados/Formatador.php`:

```php
<?php

declare(strict_types=1);

namespace App\Dados;

use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use NumberFormatter;

final readonly class Formatador
{
    public function __construct(
        private string $locale,
        private string $moeda,
    ) {}

    public function moeda(float|int|string $valor): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency((float) $valor, $this->moeda);
    }

    public function numero(float|int|string $valor, int $casas = 0): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $casas);

        return $formatter->format((float) $valor);
    }

    public function percentual(float $valor, int $casas = 0): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::PERCENT);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $casas);

        return $formatter->format($valor);
    }

    public function data(DateTimeInterface|string $valor, int $estilo = IntlDateFormatter::SHORT): string
    {
        $formatter = new IntlDateFormatter($this->locale, $estilo, IntlDateFormatter::NONE);

        return $formatter->format($this->paraData($valor));
    }

    public function dataHora(DateTimeInterface|string $valor): string
    {
        $formatter = new IntlDateFormatter($this->locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);

        return $formatter->format($this->paraData($valor));
    }

    private function paraData(DateTimeInterface|string $valor): DateTimeInterface
    {
        return $valor instanceof DateTimeInterface ? $valor : new DateTimeImmutable($valor);
    }
}
```

Os métodos aceitam string porque é assim que os valores chegam do banco: o `preco`, que é `decimal`, vem como `'34.9'`, e o `cadastrado_em` vem como `'2026-02-02 10:00:00'`. A conversão para `float` acontece só no momento de formatar.

O locale e a moeda vêm da configuração. Em `config/common/params/dados.php`:

```php
<?php

declare(strict_types=1);

return [
    'dados' => [
        'locale' => 'pt_BR',
        'moeda' => 'BRL',
    ],
];
```

E a definição no container, em `config/common/di/dados.php`:

```php
<?php

declare(strict_types=1);

use App\Dados\Formatador;

/** @var array $params */

return [
    Formatador::class => [
        '__construct()' => [
            'locale' => $params['dados']['locale'],
            'moeda' => $params['dados']['moeda'],
        ],
    ],
];
```

Os arquivos de definição recebem a variável `$params` com todos os parâmetros. O capítulo [Container de DI](/conceitos/container-di) explica a sintaxe `'__construct()'`. Num site com vários idiomas, o locale viria do usuário ou da requisição; o capítulo [Internacionalização](/topicos/i18n) trata disso.

## Formatando na view

A action recebe o `Formatador` pelo construtor e repassa para a view. Crie `src/Web/Dados/Formatacao/Action.php`:

```php
<?php

declare(strict_types=1);

namespace App\Web\Dados\Formatacao;

use App\Dados\Formatador;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
        private Formatador $formatador,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $livros = $this->db
            ->select(['titulo', 'preco', 'cadastrado_em'])
            ->from('livro')
            ->orderBy(['cadastrado_em' => SORT_DESC])
            ->limit(5)
            ->all();

        $total = $this->db->select()->from('livro')->sum('preco');

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'livros' => $livros,
            'total' => $total,
            'formatador' => $this->formatador,
        ]);
    }
}
```

A view, `src/Web/Dados/Formatacao/template.php`, formata no momento de imprimir:

```php
<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var array[] $livros
 * @var string|null $total
 * @var App\Dados\Formatador $formatador
 */

$this->setTitle('Últimos cadastrados');
?>

<h1>Últimos cadastrados</h1>

<table>
    <tr><th>Título</th><th>Preço</th><th>Com 15% de desconto</th><th>Cadastrado em</th></tr>
    <?php foreach ($livros as $livro): ?>
        <tr>
            <td><?= Html::encode($livro['titulo']) ?></td>
            <td><?= $formatador->moeda($livro['preco']) ?></td>
            <td><?= $formatador->moeda($livro['preco'] * 0.85) ?></td>
            <td><?= $formatador->dataHora($livro['cadastrado_em']) ?></td>
        </tr>
    <?php endforeach ?>
</table>

<p>
    Valor do catálogo: <?= $formatador->moeda($total ?? 0) ?>.
    Desconto aplicado: <?= $formatador->percentual(0.15) ?>.
    Atualizado em <?= $formatador->data(new DateTimeImmutable(), IntlDateFormatter::LONG) ?>.
</p>
```

Formatar na view, e não na action ou no banco, mantém os dados como dados até o último momento. A mesma lista pode virar uma página em português, um JSON para uma API ou uma planilha, cada um com o seu formato.

A saída dos números formatados não passa pelo `Html::encode()` porque vem do ICU, não do usuário. Já o título passa.

A rota:

```php
Route::get('/dados/formatacao')
    ->action(Web\Dados\Formatacao\Action::class)
    ->name('dados/formatacao'),
```

Em `/dados/formatacao`, a primeira linha da tabela é "Vidas Secas", com `R$ 39,90`, `R$ 33,92` e `08/07/2026, 12:00`. Abaixo da tabela aparece "Valor do catálogo: R$ 589,80. Desconto aplicado: 15%." e a data do dia por extenso, como "8 de outubro de 2026".

Se o formatador é usado em muitas views, você pode evitar repassá-lo em cada action, colocando-o nos parâmetros comuns de view do `yiisoft/view` (a chave `parameters`, a mesma que já entrega `$urlGenerator`). O capítulo [Views](/estrutura/views) mostra essa configuração.

## Comparando com o Yii2

- O Yii2 tinha o componente `formatter`, com `asCurrency()`, `asDate()`, `asDecimal()` e outros, usado como `Yii::$app->formatter`. O Yii3 não tem um equivalente pronto: você usa a `intl` direto ou escreve um serviço como o `Formatador`, injetado onde precisa.
- O formatter do Yii2 aceitava formatos de data no estilo do PHP com o prefixo `php:`. Com `IntlDateFormatter`, os padrões são sempre os do ICU.
