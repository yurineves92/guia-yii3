<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var App\Web\Cadastro\CadastroForm $form
 */

$this->setTitle('Cadastro recebido');
?>

<h1>Recebemos o seu cadastro</h1>

<ul>
    <li><strong>Nome:</strong> <?= Html::encode($form->nome) ?></li>
    <li><strong>E-mail:</strong> <?= Html::encode($form->email) ?></li>
</ul>
