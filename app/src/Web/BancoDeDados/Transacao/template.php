<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var int $antes
 * @var int $depois
 * @var string|null $erro
 */

$this->setTitle('Transação');
?>

<h1>Transação</h1>

<p>Autores antes: <?= $antes ?>. Autores depois: <?= $depois ?>.</p>

<?php if ($erro !== null): ?>
    <p>O cadastro falhou e foi desfeito:</p>
    <pre><?= Html::encode($erro) ?></pre>
<?php endif ?>
