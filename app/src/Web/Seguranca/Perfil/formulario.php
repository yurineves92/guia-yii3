<?php

declare(strict_types=1);

use Yiisoft\FormModel\Field;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Web\Seguranca\Perfil\PerfilForm $form
 * @var App\Seguranca\Usuario $usuario
 */

$this->setTitle('Editar perfil');
?>

<h1>Perfil de <?= Html::encode($usuario->email) ?></h1>

<?= Html::form()
    ->post($urlGenerator->generate('seguranca/perfil', ['id' => $usuario->id]))
    ->csrf($csrf)
    ->open() ?>
    <?= Field::text($form, 'nome') ?>
    <?= Field::submitButton('Salvar') ?>
<?= Html::form()->close() ?>
