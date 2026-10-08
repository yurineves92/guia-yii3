<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $busca
 * @var string $autor
 * @var string $precoMaximo
 * @var array<string, string> $autores
 * @var iterable<array> $livros
 * @var int $total
 * @var iterable<array> $nascimentos
 */

$this->setTitle('Busca no catálogo');
?>

<h1>Busca no catálogo</h1>

<form method="get">
    <?= Html::textInput('busca', $busca, ['placeholder' => 'Texto']) ?>
    <?= Html::select('autor')->optionsData($autores)->prompt('Todos os autores')->value($autor) ?>
    <?= Html::input('number', 'preco_max', $precoMaximo, ['step' => '0.01', 'placeholder' => 'Preço máximo']) ?>
    <?= Html::submitButton('Buscar') ?>
</form>

<h2><?= $total ?> livro(s)</h2>
<ul>
    <?php foreach ($livros as $livro): ?>
        <li><?= Html::encode($livro['titulo']) ?>, de <?= Html::encode($livro['autor']) ?> (R$ <?= Html::encode($livro['preco']) ?>)</li>
    <?php endforeach ?>
</ul>

<h2>Autores</h2>
<ul>
    <?php foreach ($nascimentos as $pessoa): ?>
        <li><?= Html::encode($pessoa['nome']) ?>, nascido(a) em <?= Html::encode($pessoa['cidade']) ?> em <?= $pessoa['nascimento'] ?></li>
    <?php endforeach ?>
</ul>
