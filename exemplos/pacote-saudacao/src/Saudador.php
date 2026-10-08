<?php

declare(strict_types=1);

namespace MinhaEmpresa\Saudacao;

final readonly class Saudador
{
    public function __construct(
        private string $saudacao = 'Olá',
    ) {}

    public function saudar(string $nome): string
    {
        return "{$this->saudacao}, {$nome}!";
    }
}
