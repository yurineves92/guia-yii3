<?php

declare(strict_types=1);

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 */

$this->setTitle('Acesso negado');
?>

<h1>Acesso negado</h1>

<p>Você não tem permissão para ver esta página.</p>

<p><a href="<?= $urlGenerator->generate('seguranca/painel') ?>">Voltar ao painel</a></p>
