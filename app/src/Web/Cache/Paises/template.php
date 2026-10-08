<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var array{total: int, populacao: int, maiores: list<string>, geradoEm: string} $resumo
 */

$this->setTitle('Resumo dos países');
?>

<h1>Resumo dos países</h1>

<ul>
    <li>Países cadastrados: <?= $resumo['total'] ?></li>
    <li>População somada: <?= number_format($resumo['populacao'], 0, ',', '.') ?></li>
    <li>Mais populosos: <?= Html::encode(implode(', ', $resumo['maiores'])) ?></li>
</ul>

<p>Calculado às <?= Html::encode($resumo['geradoEm']) ?>.</p>

<?= Html::form()->post($urlGenerator->generate('cache/paises/limpar'))->csrf($csrf)->open() ?>
    <?= Html::submitButton('Limpar o cache') ?>
<?= Html::form()->close() ?>
