<?php

declare(strict_types=1);

use Yiisoft\Yii\DataView\ListView\ListView;
use Yiisoft\Yii\DataView\YiiRouter\UrlCreator;
use Yiisoft\Yii\DataView\YiiRouter\UrlParameterProvider;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 * @var Yiisoft\Data\Db\QueryDataReader $livros
 * @var App\Dados\Formatador $formatador
 */

$this->setTitle('Vitrine');
?>

<h1>Vitrine</h1>

<?= ListView::widget()
    ->dataReader($livros)
    ->urlCreator(new UrlCreator($urlGenerator))
    ->urlParameterProvider(new UrlParameterProvider($currentRoute))
    ->itemView(__DIR__ . '/_livro.php')
    ->itemViewParameters(['formatador' => $formatador])
    ->listAttributes(['class' => 'vitrine'])
    ->summaryTemplate('Página {currentPage} de {totalPages}')
    ->offsetPaginationConfig([
        'labelFirst()' => [null],
        'labelLast()' => [null],
        'labelPrevious()' => ['Anterior'],
        'labelNext()' => ['Próxima'],
    ]) ?>
