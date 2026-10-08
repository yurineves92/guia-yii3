<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DateTimeImmutable;

final readonly class ConcluirTarefa
{
    public function __construct(
        private TarefaRepositoryInterface $tarefas,
    ) {}

    /**
     * @throws TarefaNaoEncontradaException
     * @throws TarefaJaConcluidaException
     */
    public function executar(int $id): Tarefa
    {
        $tarefa = $this->tarefas->buscar($id) ?? throw new TarefaNaoEncontradaException($id);

        $tarefa->concluir(new DateTimeImmutable());
        $this->tarefas->salvar($tarefa);

        return $tarefa;
    }
}
