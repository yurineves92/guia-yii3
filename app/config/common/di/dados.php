<?php

declare(strict_types=1);

use App\Dados\Formatador;

/** @var array $params */

return [
    Formatador::class => [
        '__construct()' => [
            'locale' => $params['dados']['locale'],
            'moeda' => $params['dados']['moeda'],
        ],
    ],
];
