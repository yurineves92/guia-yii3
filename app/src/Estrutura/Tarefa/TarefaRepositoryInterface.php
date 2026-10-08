<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

interface TarefaRepositoryInterface
{
    public function buscar(int $id): ?Tarefa;

    /**
     * @return Tarefa[]
     */
    public function todas(): array;

    public function salvar(Tarefa $tarefa): void;
}
