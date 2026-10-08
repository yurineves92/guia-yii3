<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Cadastro\CadastroForm $form
 */

$this->setTitle('Cadastro');
?>

<h1>Cadastro</h1>

<?= Html::form()->post($urlGenerator->generate('cadastro'))->csrf($csrf)->open() ?>
    <?= Field::text($form, 'nome') ?>
    <?= Field::email($form, 'email') ?>
    <?= Field::submitButton('Enviar') ?>
<?= Html::form()->close() ?>
