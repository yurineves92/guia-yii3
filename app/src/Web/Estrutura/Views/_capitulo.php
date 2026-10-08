<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array{titulo: string, palavras: int} $capitulo
 */
?>
<li><?= Html::encode($capitulo['titulo']) ?> (<?= $capitulo['palavras'] ?> palavras)</li>
