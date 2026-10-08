<?php

declare(strict_types=1);

/**
 * @var Yiisoft\View\WebView $this
 * @var array<array{titulo: string, palavras: int}> $capitulos
 */

$this->setTitle('Capítulos');
$this->setBlock('rodape', 'Página gerada com um layout próprio da seção.');
?>

<h1>Capítulos</h1>

<ul>
    <?php foreach ($capitulos as $capitulo): ?>
        <?= $this->render('./_capitulo', ['capitulo' => $capitulo]) ?>
    <?php endforeach ?>
</ul>
