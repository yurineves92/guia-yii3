<?php

declare(strict_types=1);

use App\Web\Formularios\Inscricao\InscricaoForm;
use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var InscricaoForm $form
 */

$this->setTitle('Inscrição');
?>

<h1>Inscrição no evento</h1>

<?= Html::form()->post($urlGenerator->generate('formularios/inscricao'))->csrf($csrf)->open() ?>
    <?= Field::text($form, 'nome') ?>
    <?= Field::text($form, 'cpf') ?>
    <?= Field::email($form, 'email') ?>
    <?= Field::email($form, 'confirmacaoEmail') ?>
    <?= Field::number($form, 'idade') ?>
    <?= Field::radioList($form, 'tipo')->items(InscricaoForm::TIPOS) ?>
    <?= Field::text($form, 'instituicao') ?>
    <?= Field::url($form, 'site') ?>
    <?= Field::submitButton('Inscrever') ?>
<?= Html::form()->close() ?>
