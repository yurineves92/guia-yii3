<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Paginator\OffsetPaginator $paginador
 * @var array[] $livros
 */

$this->setTitle('Livros por título');

$url = static fn (int $pagina): string => $urlGenerator->generate(
    'dados/paginacao',
    queryParameters: $pagina === 1 ? [] : ['pagina' => $pagina],
);
$inicio = $paginador->getOffset() + 1;
$fim = $paginador->getOffset() + $paginador->getCurrentPageSize();
?>

<h1>Livros por título</h1>

<p>Livros <?= $inicio ?> a <?= $fim ?> de <?= $paginador->getTotalItems() ?></p>

<ul>
    <?php foreach ($livros as $livro): ?>
        <li><?= Html::encode($livro['titulo']) ?> (<?= Html::encode($livro['ano']) ?>)</li>
    <?php endforeach ?>
</ul>

<nav>
    <?php if (!$paginador->isOnFirstPage()): ?>
        <a href="<?= $url($paginador->getCurrentPage() - 1) ?>">Anterior</a>
    <?php endif ?>

    <?php for ($pagina = 1; $pagina <= $paginador->getTotalPages(); $pagina++): ?>
        <?php if ($pagina === $paginador->getCurrentPage()): ?>
            <strong><?= $pagina ?></strong>
        <?php else: ?>
            <a href="<?= $url($pagina) ?>"><?= $pagina ?></a>
        <?php endif ?>
    <?php endfor ?>

    <?php if (!$paginador->isOnLastPage()): ?>
        <a href="<?= $url($paginador->getCurrentPage() + 1) ?>">Próxima</a>
    <?php endif ?>
</nav>
