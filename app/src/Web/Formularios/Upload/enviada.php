<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $legenda
 * @var string $url
 */

$this->setTitle('Foto enviada');
?>

<h1>Foto enviada</h1>

<figure>
    <?= Html::img($url, $legenda)->width(400) ?>
    <figcaption><?= Html::encode($legenda) ?></figcaption>
</figure>
