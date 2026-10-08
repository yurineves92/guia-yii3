<?php

declare(strict_types=1);

use App\Estrutura\LojaParams;
use App\Estrutura\Tarefa\DbTarefaRepository;
use App\Estrutura\Tarefa\TarefaRepositoryInterface;

/** @var array $params */

return [
    LojaParams::class => [
        '__construct()' => [
            'nome' => $params['estrutura']['loja']['nome'],
            'email' => $params['estrutura']['loja']['email'],
        ],
    ],

    TarefaRepositoryInterface::class => DbTarefaRepository::class,
];
