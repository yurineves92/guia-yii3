<?php

declare(strict_types=1);

use App\Topicos\Email\BoasVindasEmail;
use App\Topicos\LongaDuracao\ContextoDaRequisicao;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\Reference;
use Yiisoft\Mailer\FileMailer;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\IntlMessageFormatter;
use Yiisoft\Translator\Message\Php\MessageSource;

return [
    'topicos.translation.categorySource' => [
        'definition' => static function (Aliases $aliases): CategorySource {
            $messageSource = new MessageSource($aliases->get('@root/messages'));

            return new CategorySource('topicos', $messageSource, new IntlMessageFormatter());
        },
        'tags' => ['translation.categorySource'],
    ],

    BoasVindasEmail::class => [
        '__construct()' => [
            'mailer' => Reference::to(FileMailer::class),
            'remetente' => 'nao-responda@guia.test',
        ],
    ],

    ContextoDaRequisicao::class => [
        'reset' => function () {
            $this->idDaRequisicao = null;
        },
    ],
];
