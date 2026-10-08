<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string|null $busca
 * @var float|null $precoMaximo
 * @var array[] $livros
 * @var array[] $totais
 */

$this->setTitle('Livros');
?>

<h1>Livros</h1>

<form method="get">
    <input type="search" name="busca" value="<?= Html::encodeAttribute($busca) ?>" placeholder="Título">
    <input type="number" name="preco_max" value="<?= Html::encodeAttribute($precoMaximo) ?>" step="0.01" placeholder="Preço máximo">
    <button>Filtrar</button>
</form>

<table>
    <tr><th>Título</th><th>Autor</th><th>Ano</th><th>Preço</th></tr>
    <?php foreach ($livros as $livro): ?>
        <tr>
            <td><?= Html::encode($livro['titulo']) ?></td>
            <td><?= Html::encode($livro['autor']) ?></td>
            <td><?= Html::encode($livro['ano']) ?></td>
            <td><?= Html::encode($livro['preco']) ?></td>
        </tr>
    <?php endforeach ?>
</table>

<h2>Por autor</h2>
<ul>
    <?php foreach ($totais as $total): ?>
        <li>
            <?= Html::encode($total['autor']) ?>: <?= Html::encode($total['livros']) ?> livros,
            preço médio <?= number_format((float) $total['preco_medio'], 2, ',', '.') ?>
        </li>
    <?php endforeach ?>
</ul>
