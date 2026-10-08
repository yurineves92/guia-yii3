<?php

declare(strict_types=1);

namespace App\Topicos\Qualidade;

final class Saudacao
{
    public function __construct(private string $nome) {}

    public function texto(): string
    {
        if (str_contains($this->nome, 'Dr.')) {
            return 'Olá, ' . $this->nome . '. Seja bem-vindo.';
        }

        return 'Oi, ' . $this->nome . '!';
    }
}
