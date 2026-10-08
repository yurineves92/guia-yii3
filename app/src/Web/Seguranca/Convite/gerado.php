<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var string $token
 */

$this->setTitle('Convite');

$link = $urlGenerator->generate('seguranca/convite', queryParameters: ['token' => $token]);
?>

<h1>Link de convite</h1>

<p>O link vale por 10 minutos:</p>

<p><?= Html::a($link, $link) ?></p>
