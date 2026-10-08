<?php

declare(strict_types=1);

use Yiisoft\Html\Html;
use Yiisoft\Yii\DataView\Pagination\KeysetPagination;
use Yiisoft\Yii\DataView\Pagination\PaginationContext;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Paginator\KeysetPaginator $paginador
 */

$this->setTitle('Livros por slug');
?>

<h1>Livros por slug</h1>

<ul>
    <?php foreach ($paginador->read() as $livro): ?>
        <li><?= Html::encode($livro['titulo']) ?> (<?= Html::encode($livro['slug']) ?>)</li>
    <?php endforeach ?>
</ul>

<?= KeysetPagination::widget()
    ->paginator($paginador)
    ->context(new PaginationContext(
        nextUrlPattern: $urlGenerator->generate(
            'dados/keyset',
            queryParameters: ['depois' => PaginationContext::URL_PLACEHOLDER],
        ),
        previousUrlPattern: $urlGenerator->generate(
            'dados/keyset',
            queryParameters: ['antes' => PaginationContext::URL_PLACEHOLDER],
        ),
        firstPageUrl: $urlGenerator->generate('dados/keyset'),
    ))
    ->labelPrevious('Anterior')
    ->labelNext('Próxima') ?>
