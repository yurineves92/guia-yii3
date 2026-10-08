<?php

declare(strict_types=1);

namespace App\Web\Api\Erro;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Lança uma exceção de propósito, para mostrar como a API responde a um erro inesperado.
 */
final readonly class Action
{
    public function __invoke(): ResponseInterface
    {
        throw new RuntimeException('Falha simulada: a conexão com o serviço de estoque caiu.');
    }
}
