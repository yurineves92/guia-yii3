<?php

declare(strict_types=1);

namespace App\Conceitos\Imutabilidade;

final class Filtro
{
    private string $termo = '';
    private int $limite = 20;

    public function withTermo(string $termo): self
    {
        $new = clone $this;
        $new->termo = $termo;
        return $new;
    }

    public function withLimite(int $limite): self
    {
        $new = clone $this;
        $new->limite = $limite;
        return $new;
    }

    public function descrever(): string
    {
        return "termo='{$this->termo}', limite={$this->limite}";
    }
}
