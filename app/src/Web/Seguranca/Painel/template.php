<?php

declare(strict_types=1);

use App\Seguranca\Rbac\Permissao;
use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Yii\View\Renderer\Csrf $csrf
 * @var App\Seguranca\Usuario $usuario
 * @var string[] $papeis
 * @var Yiisoft\User\CurrentUser $currentUser
 * @var string|null $aviso
 */

$this->setTitle('Painel');
?>

<h1>Olá, <?= Html::encode($usuario->nome) ?></h1>

<?php if ($aviso !== null): ?>
    <p><strong><?= Html::encode($aviso) ?></strong></p>
<?php endif ?>

<p>Papéis: <?= Html::encode(implode(', ', $papeis) ?: 'nenhum') ?></p>

<ul>
    <li>
        <a href="<?= $urlGenerator->generate('seguranca/perfil', ['id' => $usuario->id]) ?>">Editar meu perfil</a>
    </li>
    <li><a href="<?= $urlGenerator->generate('seguranca/senha') ?>">Trocar a senha</a></li>
    <li><a href="<?= $urlGenerator->generate('seguranca/convite/novo') ?>">Gerar um link de convite</a></li>
    <?php if ($currentUser->can(Permissao::VerRelatorios)): ?>
        <li><a href="<?= $urlGenerator->generate('seguranca/relatorios') ?>">Relatórios</a></li>
    <?php endif ?>
</ul>

<?= Html::form()->post($urlGenerator->generate('seguranca/logout'))->csrf($csrf)->open() ?>
    <?= Html::submitButton('Sair') ?>
<?= Html::form()->close() ?>
