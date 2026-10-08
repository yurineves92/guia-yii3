<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DomainException;

final class TarefaNaoEncontradaException extends DomainException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Tarefa %d não encontrada.', $id));
    }
}
