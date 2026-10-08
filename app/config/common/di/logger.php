<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\ReferencesArray;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\Log\Target\File\FileTarget;

/** @var array $params */

return [
    LoggerInterface::class => [
        'class' => Logger::class,
        '__construct()' => [
            'targets' => ReferencesArray::from([
                StreamTarget::class,
                FileTarget::class,
                'log.pagamentos',
            ]),
        ],
    ],

    'log.pagamentos' => static fn(Aliases $aliases) => (new FileTarget($aliases->get('@runtime/logs/pagamentos.log')))
        ->setCategories(['pagamentos']),
];
