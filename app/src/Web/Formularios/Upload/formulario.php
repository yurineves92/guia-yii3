<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Formularios\Upload\FotoForm $form
 */

$this->setTitle('Enviar foto');
?>

<h1>Enviar foto</h1>

<?= Html::form()
    ->post($urlGenerator->generate('formularios/upload'))
    ->enctypeMultipartFormData()
    ->csrf($csrf)
    ->open() ?>
    <?= Field::text($form, 'legenda') ?>
    <?= Field::file($form, 'foto')->accept('image/jpeg,image/png') ?>
    <?= Field::submitButton('Enviar') ?>
<?= Html::form()->close() ?>
