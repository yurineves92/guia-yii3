<?php

declare(strict_types=1);

use MinhaEmpresa\Saudacao\Saudador;

/** @var array $params */

return [
    Saudador::class => [
        '__construct()' => [
            'saudacao' => $params['minha-empresa/saudacao']['saudacao'],
        ],
    ],
];
