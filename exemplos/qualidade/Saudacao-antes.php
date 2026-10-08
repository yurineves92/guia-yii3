<?php

declare(strict_types=1);

namespace App\Topicos\Qualidade;

final class Saudacao
{
    private string $nome;

    public function __construct(string $nome)
    {
        $this->nome = $nome;
    }

    public function texto(): string
    {
        if (strpos($this->nome, 'Dr.') !== false) {
            return 'Olá, ' . $this->nome . '. Seja bem-vindo.';
        }

        return 'Oi, ' . $this->nome . '!';
    }
}
