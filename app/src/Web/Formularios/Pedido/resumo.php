<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var App\Web\Formularios\Pedido\PedidoForm $form
 */

$this->setTitle('Pedido recebido');
?>

<h1>Pedido de <?= Html::encode($form->cliente) ?></h1>

<p>Entrega em <?= Html::encode($form->endereco->cidade) ?>, CEP <?= Html::encode($form->endereco->cep) ?>.</p>

<ul>
    <?php foreach ($form->itens as $item): ?>
        <li><?= $item->quantidade ?> x <?= Html::encode($item->produto) ?></li>
    <?php endforeach ?>
</ul>
