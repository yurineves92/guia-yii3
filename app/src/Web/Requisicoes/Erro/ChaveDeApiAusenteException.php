<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Erro;

use RuntimeException;
use Yiisoft\FriendlyException\FriendlyExceptionInterface;

final class ChaveDeApiAusenteException extends RuntimeException implements FriendlyExceptionInterface
{
    public function __construct()
    {
        parent::__construct('A chave da API de frete não foi configurada.');
    }

    public function getName(): string
    {
        return 'Chave da API de frete ausente';
    }

    public function getSolution(): ?string
    {
        return <<<'MD'
            Defina a variável de ambiente `FRETE_API_KEY` no arquivo `.env`:

            ```bash
            FRETE_API_KEY=sua-chave-aqui
            ```

            Depois reinicie o servidor.
            MD;
    }
}
