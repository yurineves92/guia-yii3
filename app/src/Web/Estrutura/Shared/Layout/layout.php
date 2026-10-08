<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var App\Shared\ApplicationParams $applicationParams
 * @var string $content
 * @var string $secao
 * @var array<string, string> $menu
 */

$this->beginPage();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="<?= Html::encode($applicationParams->charset) ?>">
    <title><?= Html::encode($this->getTitle()) ?> | <?= Html::encode($secao) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<header>
    <strong><?= Html::encode($secao) ?></strong>
    <nav>
        <?php foreach ($menu as $rotulo => $url): ?>
            <a href="<?= Html::encodeAttribute($url) ?>"><?= Html::encode($rotulo) ?></a>
        <?php endforeach ?>
    </nav>
</header>

<main>
    <?= $content ?>
</main>

<?php if ($this->hasBlock('rodape')): ?>
    <footer><?= $this->getBlock('rodape') ?></footer>
<?php endif ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
