<?php

declare(strict_types=1);

use App\Web\Estrutura\Widget\Alerta;
use App\Web\Estrutura\Widget\Cartao;
use App\Web\Estrutura\Widget\MenuSecao;

/**
 * @var Yiisoft\View\WebView $this
 */

$this->setTitle('Widgets');
?>

<?= MenuSecao::widget()->itens([
    'Widgets' => 'estrutura/widgets',
    'Views' => 'estrutura/views',
    'Tarefas' => 'estrutura/tarefas',
]) ?>

<h1>Widgets</h1>

<?= Alerta::widget()->tipo('sucesso')->mensagem('Tudo certo por aqui.') ?>
<?= Alerta::widget(config: ['tipo()' => ['erro']])->mensagem('Algo deu <errado>.') ?>
<?= Alerta::widget() ?>

<?php Cartao::widget()->titulo('Um cartão')->begin() ?>
    <p>Este conteúdo fica entre o <code>begin()</code> e o <code>end()</code>.</p>
<?= Cartao::end() ?>
