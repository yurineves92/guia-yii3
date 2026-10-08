<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $titulo
 */

$this->setTitle($titulo);
?>

<h1><?= Html::encode($titulo) ?></h1>
