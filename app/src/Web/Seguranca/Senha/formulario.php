<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Seguranca\Senha\TrocarSenhaForm $form
 */

$this->setTitle('Trocar a senha');
?>

<h1>Trocar a senha</h1>

<?= Html::form()->post($urlGenerator->generate('seguranca/senha'))->csrf($csrf)->open() ?>
    <?= Field::password($form, 'senhaAtual') ?>
    <?= Field::password($form, 'novaSenha')->hint('De 12 a 64 caracteres. Uma frase com várias palavras funciona bem.') ?>
    <?= Field::password($form, 'confirmacao') ?>
    <?= Field::submitButton('Trocar') ?>
<?= Html::form()->close() ?>
