<?php

declare(strict_types=1);

use App\Web\Formularios\Inscricao\InscricaoForm;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var InscricaoForm $form
 */

$this->setTitle('Inscrição confirmada');
?>

<h1>Inscrição confirmada</h1>

<p>
    <?= Html::encode($form->nome) ?>, <?= $form->idade ?> anos,
    ingresso <?= Html::encode(InscricaoForm::TIPOS[$form->tipo]) ?>.
</p>
