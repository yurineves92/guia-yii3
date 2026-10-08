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
