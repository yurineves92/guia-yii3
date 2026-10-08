<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var string|null $mensagem
 */

$this->setTitle('Recado');
?>

<h1>Recado</h1>

<?php if ($mensagem !== null): ?>
    <p class="aviso"><?= Html::encode($mensagem) ?></p>
<?php endif ?>

<?= Html::form()->post($urlGenerator->generate('requisicoes/recado'))->csrf($csrf)->open() ?>
    <?= Html::submitButton('Salvar recado') ?>
<?= Html::form()->close() ?>
