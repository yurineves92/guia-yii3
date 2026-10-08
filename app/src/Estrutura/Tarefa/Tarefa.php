<?php

declare(strict_types=1);

namespace App\Estrutura\Tarefa;

use DateTimeImmutable;

final class Tarefa
{
    public function __construct(
        private readonly string $titulo,
        private ?int $id = null,
        private ?DateTimeImmutable $concluidaEm = null,
    ) {}

    public function concluir(DateTimeImmutable $quando): void
    {
        if ($this->concluidaEm !== null) {
            throw new TarefaJaConcluidaException($this);
        }

        $this->concluidaEm = $quando;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function definirId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function getConcluidaEm(): ?DateTimeImmutable
    {
        return $this->concluidaEm;
    }

    public function estaConcluida(): bool
    {
        return $this->concluidaEm !== null;
    }
}
