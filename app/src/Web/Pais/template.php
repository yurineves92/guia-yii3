<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Paginator\OffsetPaginator $paginator
 */

$this->setTitle('Países');
?>

<h1>Países</h1>

<ul>
    <?php foreach ($paginator->read() as $pais): ?>
        <li>
            <?= Html::encode($pais['nome']) ?> (<?= Html::encode($pais['codigo']) ?>):
            <?= number_format((int) $pais['populacao'], 0, ',', '.') ?>
        </li>
    <?php endforeach ?>
</ul>

<nav>
    <?php for ($pagina = 1; $pagina <= $paginator->getTotalPages(); $pagina++): ?>
        <?php if ($pagina === $paginator->getCurrentPage()): ?>
            <strong><?= $pagina ?></strong>
        <?php else: ?>
            <a href="<?= $urlGenerator->generate('pais', queryParameters: ['pagina' => $pagina]) ?>"><?= $pagina ?></a>
        <?php endif ?>
    <?php endfor ?>
</nav>
