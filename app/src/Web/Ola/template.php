<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $mensagem
 */

$this->setTitle('Olá');
?>

<h1><?= Html::encode($mensagem) ?></h1>
