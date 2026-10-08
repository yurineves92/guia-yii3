<?php

declare(strict_types=1);

use App\Web\Formularios\Contato\ContatoForm;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var ContatoForm $form
 */

$this->setTitle('Mensagem enviada');
?>

<h1>Recebemos a sua mensagem</h1>

<p>
    Obrigado, <?= Html::encode($form->nome) ?>.
    Assunto: <?= Html::encode(ContatoForm::ASSUNTOS[$form->assunto]) ?>.
    <?= $form->receberNovidades ? 'Você vai receber nossas novidades.' : 'Você não vai receber novidades.' ?>
</p>
