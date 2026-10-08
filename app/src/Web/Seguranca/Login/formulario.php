<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Seguranca\Login\LoginForm $form
 */

$this->setTitle('Entrar');
?>

<h1>Entrar</h1>

<?= Html::form()->post($urlGenerator->generate('seguranca/login'))->csrf($csrf)->open() ?>
    <?= Field::email($form, 'email') ?>
    <?= Field::password($form, 'senha') ?>
    <?= Field::submitButton('Entrar') ?>
<?= Html::form()->close() ?>
