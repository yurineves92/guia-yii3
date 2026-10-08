<?php

declare(strict_types=1);

namespace App\Topicos\LongaDuracao;

/**
 * Guarda um dado que só vale durante uma requisição.
 */
final class ContextoDaRequisicao
{
    private ?string $idDaRequisicao = null;

    public function definirId(string $id): void
    {
        $this->idDaRequisicao = $id;
    }

    public function id(): ?string
    {
        return $this->idDaRequisicao;
    }
}
