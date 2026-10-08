<?php

declare(strict_types=1);

use App\Web\Estrutura\Middleware\ExigeChaveMiddleware;

/** @var array $params */

return [
    ExigeChaveMiddleware::class => [
        '__construct()' => [
            'chave' => $params['estrutura']['chaveDoPainel'],
        ],
    ],
];
