<?php

declare(strict_types=1);

use Yiisoft\Html\Html;

/**
 * @var Yiisoft\View\WebView $this
 * @var Yiisoft\Router\UrlGeneratorInterface $urlGenerator
 * @var Yiisoft\Translator\TranslatorInterface $t
 * @var string[] $idiomas
 * @var DateTimeImmutable $hoje
 */

$this->setTitle('i18n');
?>

<p lang="<?= Html::encodeAttribute($t->getLocale()) ?>">
    <?= Html::encode($t->translate('Hello, {name}!', ['name' => 'Ana'])) ?><br>
    <?= Html::encode($t->translate('You have {count, plural, =0{no new messages} one{# new message} other{# new messages}}.', ['count' => 3])) ?><br>
    <?= Html::encode($t->translate('Brazil has {population, number} inhabitants.', ['population' => 212_000_000])) ?><br>
    <?= Html::encode($t->translate('Today is {today, date, long}.', ['today' => $hoje])) ?>
</p>

<nav>
    <?php foreach ($idiomas as $idioma): ?>
        <?= Html::a($idioma, $urlGenerator->generate('topicos/i18n', queryParameters: ['idioma' => $idioma])) ?>
    <?php endforeach ?>
</nav>
