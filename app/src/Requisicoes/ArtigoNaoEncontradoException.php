<?php

declare(strict_types=1);

namespace App\Requisicoes;

use RuntimeException;

final class ArtigoNaoEncontradoException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Artigo $id não encontrado.");
    }
}
