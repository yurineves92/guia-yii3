<?php

declare(strict_types=1);

use Yiisoft\Yii\DataView\GridView\Column\ActionButton;
use Yiisoft\Yii\DataView\GridView\Column\ActionColumn;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;
use Yiisoft\Yii\DataView\GridView\Column\SerialColumn;
use Yiisoft\Yii\DataView\GridView\GridView;
use Yiisoft\Yii\DataView\YiiRouter\UrlCreator;
use Yiisoft\Yii\DataView\YiiRouter\UrlParameterProvider;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Router\CurrentRoute $currentRoute
 * @var Yiisoft\Data\Paginator\OffsetPaginator $paginador
 * @var App\Dados\Formatador $formatador
 */

$this->setTitle('Catálogo');
?>

<h1>Catálogo</h1>

<?= GridView::widget()
    ->dataReader($paginador)
    ->urlCreator(new UrlCreator($urlGenerator))
    ->urlParameterProvider(new UrlParameterProvider($currentRoute))
    ->summaryTemplate('Livros {begin} a {end} de {totalCount}')
    ->noResultsText('Nenhum livro encontrado.')
    ->offsetPaginationConfig([
        'labelFirst()' => ['Primeira'],
        'labelPrevious()' => ['Anterior'],
        'labelNext()' => ['Próxima'],
        'labelLast()' => ['Última'],
    ])
    ->columns(
        new SerialColumn(header: '#'),
        new DataColumn('titulo', header: 'Título'),
        new DataColumn('autor', header: 'Autor'),
        new DataColumn('ano', header: 'Ano'),
        new DataColumn(
            'preco',
            header: 'Preço',
            content: static fn (array $livro): string => $formatador->moeda($livro['preco']),
        ),
        new ActionColumn(
            header: 'Ações',
            buttons: [
                'ver' => new ActionButton(
                    content: 'Ver',
                    url: static fn (array $livro): string => $urlGenerator->generate(
                        'banco-de-dados/livro',
                        ['id' => $livro['id']],
                    ),
                ),
            ],
        ),
    ) ?>
