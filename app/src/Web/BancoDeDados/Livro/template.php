<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var App\BancoDeDados\Livro $livro
 */

$this->setTitle($livro->titulo);
?>

<h1><?= Html::encode($livro->titulo) ?></h1>

<ul>
    <li>Autor: <?= Html::encode($livro->getAutor()->nome) ?></li>
    <li>Ano: <?= $livro->ano ?></li>
    <li>Preço: R$ <?= Html::encode($livro->preco) ?></li>
    <li>Cadastrado em: <?= $livro->cadastrado_em->format('d/m/Y H:i') ?></li>
</ul>
