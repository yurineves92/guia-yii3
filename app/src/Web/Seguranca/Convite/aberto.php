<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var string $convidadoPor
 * @var int $expira
 */

$this->setTitle('Convite');
?>

<h1>Você foi convidado por <?= Html::encode($convidadoPor) ?></h1>

<p>Este convite expira às <?= date('H:i', $expira) ?>.</p>
