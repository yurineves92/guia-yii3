<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DomainException;

final class TarefaJaConcluidaException extends DomainException
{
    public function __construct(Tarefa $tarefa)
    {
        parent::__construct(sprintf('A tarefa "%s" já foi concluída.', $tarefa->getTitulo()));
    }
}
