<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var App\BancoDeDados\Autor[] $autores
 */

$this->setTitle('Autores');
?>

<h1>Autores</h1>

<?php foreach ($autores as $autor): ?>
    <h2><?= Html::encode($autor->nome) ?></h2>
    <ul>
        <?php foreach ($autor->getLivros() as $livro): ?>
            <li>
                <?= Html::a(
                    Html::encode($livro->titulo),
                    $urlGenerator->generate('banco-de-dados/livro', ['id' => $livro->id]),
                ) ?>
                (<?= $livro->ano ?>)
            </li>
        <?php endforeach ?>
    </ul>
<?php endforeach ?>
