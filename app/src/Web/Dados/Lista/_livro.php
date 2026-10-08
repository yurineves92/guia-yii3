<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var array $data
 * @var Yiisoft\Yii\DataView\ListView\ListItemContext $context
 * @var App\Dados\Formatador $formatador
 */
?>
<strong><?= Html::encode($data['titulo']) ?></strong>
<?= Html::encode($data['autor']) ?>, <?= $data['ano'] ?>.
<?= $formatador->moeda($data['preco']) ?>
