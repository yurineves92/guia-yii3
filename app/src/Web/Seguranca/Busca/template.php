<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var string $termo
 * @var array[] $paises
 * @var string $sql
 */

$this->setTitle('Busca de países');
?>

<h1>Busca de países</h1>

<?= Html::form()->get($urlGenerator->generate('seguranca/busca'))->open() ?>
    <?= Html::textInput('q', $termo) ?>
    <?= Html::submitButton('Buscar') ?>
<?= Html::form()->close() ?>

<?php if ($termo !== ''): ?>
    <p>Resultados para: <?= Html::encode($termo) ?></p>

    <ul>
        <?php foreach ($paises as $pais): ?>
            <li><?= Html::encode($pais['nome']) ?> (<?= Html::encode($pais['codigo']) ?>)</li>
        <?php endforeach ?>
    </ul>

    <p>SQL executado: <code><?= Html::encode($sql) ?></code></p>
<?php endif ?>
