<?php

declare(strict_types=1);

use App\Console;
use App\Topicos;

return [
    'hello' => Console\HelloCommand::class,
    'pais:listar|paises' => Topicos\Console\ListarPaisesCommand::class,
    'email:boas-vindas' => Topicos\Console\EnviarBoasVindasCommand::class,
];
