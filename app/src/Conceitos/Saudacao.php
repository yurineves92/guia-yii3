<?php

declare(strict_types=1);

namespace App\Conceitos;

final readonly class Saudacao
{
    public function __construct(
        private string $mensagem,
        private int $itensPorPagina,
    ) {}

    public function texto(): string
    {
        return "{$this->mensagem} (mostrando {$this->itensPorPagina} itens por página)";
    }
}
