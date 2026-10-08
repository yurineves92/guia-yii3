<?php

declare(strict_types=1);

use Yiisoft\Cookies\CookieSigner;

/** @var array $params */

return [
    CookieSigner::class => [
        '__construct()' => [
            'key' => $params['requisicoes']['cookieKey'],
        ],
    ],
];
