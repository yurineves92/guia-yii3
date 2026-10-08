<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var array<string, string> $artigos
 * @var string $busca
 */

$this->setTitle('Artigos');
?>

<h1>Artigos</h1>

<?php if ($busca !== ''): ?>
    <p>Resultados para "<?= Html::encode($busca) ?>":</p>
<?php endif ?>

<ul>
    <?php foreach ($artigos as $slug => $titulo): ?>
        <li>
            <a href="<?= $urlGenerator->generate('estrutura/artigo', ['slug' => $slug]) ?>"><?= Html::encode($titulo) ?></a>
        </li>
    <?php endforeach ?>
</ul>
