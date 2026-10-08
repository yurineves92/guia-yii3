<?php

declare(strict_types=1);

use App\Web\Formularios\Contato\ContatoForm;
use Yiisoft\FormModel\FieldFactory;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var ContatoForm $form
 */

$this->setTitle('Contato');

$this->registerCss(<<<CSS
    .campo { margin-bottom: 1rem; }
    .campo label { display: block; font-weight: bold; }
    .campo-dica { color: #666; font-size: .9em; }
    .campo-erro { color: #b00020; }
    .invalido { border-color: #b00020; }
    CSS);

$field = new FieldFactory('guia');
?>

<h1>Fale conosco</h1>

<?= Html::form()->post($urlGenerator->generate('formularios/contato'))->csrf($csrf)->open() ?>
    <?= $field->text($form, 'nome') ?>
    <?= $field->email($form, 'email') ?>
    <?= $field->select($form, 'assunto')->optionsData(ContatoForm::ASSUNTOS)->prompt('Selecione...') ?>
    <?= $field->textarea($form, 'mensagem')->rows(5) ?>
    <?= $field->checkbox($form, 'receberNovidades') ?>
    <?= $field->submitButton('Enviar') ?>
<?= Html::form()->close() ?>
