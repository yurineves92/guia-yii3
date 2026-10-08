<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Formularios\Pedido\PedidoForm $form
 */

$this->setTitle('Novo pedido');

$linhas = max(3, count($form->itens));
$erros = $form->isValidated() ? $form->getValidationResult()->getErrorMessagesIndexedByPath() : [];
?>

<h1>Novo pedido</h1>

<?= Html::form()->post($urlGenerator->generate('formularios/pedido'))->csrf($csrf)->open() ?>
    <?= Field::text($form, 'cliente') ?>

    <fieldset>
        <legend>Endereço de entrega</legend>
        <?= Field::text($form, 'endereco[cep]') ?>
        <?= Field::text($form, 'endereco[cidade]') ?>
    </fieldset>

    <table>
        <tr>
            <th>Produto</th>
            <th>Quantidade</th>
        </tr>
        <?php for ($i = 0; $i < $linhas; $i++): ?>
            <tr>
                <td>
                    <?= Field::text($form, "itens[$i][produto]")
                        ->hideLabel()
                        ->error($erros["itens.$i.produto"][0] ?? null) ?>
                </td>
                <td>
                    <?= Field::number($form, "itens[$i][quantidade]")
                        ->hideLabel()
                        ->error($erros["itens.$i.quantidade"][0] ?? null) ?>
                </td>
            </tr>
        <?php endfor ?>
    </table>
    <?= isset($erros['itens']) ? Html::div($erros['itens'][0]) : '' ?>

    <?= Field::submitButton('Fechar pedido') ?>
<?= Html::form()->close() ?>
