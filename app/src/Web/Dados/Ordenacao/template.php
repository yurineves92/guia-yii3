<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Data\Reader\Sort $sort
 * @var iterable<array> $livros
 */

$this->setTitle('Livros ordenados');

$cabecalho = static function (string $campo, string $rotulo) use ($sort, $urlGenerator): string {
    $atual = $sort->getOrder()[$campo] ?? null;
    $proxima = match ($atual) {
        'asc' => 'desc',
        'desc' => 'asc',
        null => $sort->getDefaultOrder()[$campo],
    };
    $url = $urlGenerator->generate('dados/ordenacao', queryParameters: [
        'ordem' => ($proxima === 'desc' ? '-' : '') . $campo,
    ]);

    return Html::a($rotulo . ($atual === null ? '' : " ($atual)"), $url)->render();
};
?>

<h1>Livros ordenados</h1>

<table>
    <tr>
        <th><?= $cabecalho('titulo', 'Título') ?></th>
        <th><?= $cabecalho('autor', 'Autor') ?></th>
        <th><?= $cabecalho('ano', 'Ano') ?></th>
        <th><?= $cabecalho('preco', 'Preço') ?></th>
    </tr>
    <?php foreach ($livros as $livro): ?>
        <tr>
            <td><?= Html::encode($livro['titulo']) ?></td>
            <td><?= Html::encode($livro['autor']) ?></td>
            <td><?= Html::encode($livro['ano']) ?></td>
            <td><?= Html::encode($livro['preco']) ?></td>
        </tr>
    <?php endforeach ?>
</table>
