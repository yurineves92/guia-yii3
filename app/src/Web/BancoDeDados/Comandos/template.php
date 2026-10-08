<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $driver
 * @var int|string $total
 * @var string[] $autores
 * @var array[] $antigos
 * @var array|null $livro
 */

$this->setTitle('Comandos');
?>

<h1>Comandos</h1>

<p>Driver: <?= Html::encode($driver) ?>. Livros cadastrados: <?= Html::encode($total) ?>.</p>

<p>Autores: <?= Html::encode(implode(', ', $autores)) ?></p>

<h2>Publicados antes de 1900</h2>
<ul>
    <?php foreach ($antigos as $antigo): ?>
        <li><?= Html::encode($antigo['titulo']) ?> (<?= Html::encode($antigo['ano']) ?>)</li>
    <?php endforeach ?>
</ul>

<?php if ($livro !== null): ?>
    <p>Pelo slug: <?= Html::encode($livro['titulo']) ?>, R$ <?= Html::encode($livro['preco']) ?></p>
<?php endif ?>
