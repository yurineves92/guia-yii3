<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Estrutura\Tarefa\Tarefa[] $tarefas
 */

$this->setTitle('Tarefas');
?>

<h1>Tarefas</h1>

<ul>
    <?php foreach ($tarefas as $tarefa): ?>
        <li>
            <?= Html::encode($tarefa->getTitulo()) ?>
            <?php if ($tarefa->estaConcluida()): ?>
                (concluída em <?= $tarefa->getConcluidaEm()->format('d/m/Y H:i') ?>)
            <?php else: ?>
                <?= Html::form()
                    ->post($urlGenerator->generate('estrutura/tarefa/concluir', ['id' => $tarefa->getId()]))
                    ->csrf($csrf)
                    ->open() ?>
                    <?= Html::submitButton('Concluir') ?>
                <?= Html::form()->close() ?>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ul>

<?= Html::form()->post($urlGenerator->generate('estrutura/tarefa/criar'))->csrf($csrf)->open() ?>
    <?= Html::textInput('titulo')->addAttributes(['placeholder' => 'Nova tarefa']) ?>
    <?= Html::submitButton('Adicionar') ?>
<?= Html::form()->close() ?>
