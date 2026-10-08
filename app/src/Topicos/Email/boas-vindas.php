<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\View $this
 * @var string $nome
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<body>
    <h1>Olá, <?= Html::encode($nome) ?>!</h1>
    <p>Seu cadastro no Guia Yii3 foi concluído.</p>
    <p>Este é um e-mail automático. Não é preciso responder.</p>
</body>
</html>
