<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Tarefa;

use DateTimeImmutable;

/**
 * Converte uma linha da tabela `api_tarefa` no formato da versão 1 da API.
 */
final class TarefaResource
{
    /**
     * @return array{id: int, titulo: string, concluida: bool, criadaEm: string}
     */
    public static function deLinha(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'concluida' => (bool) $linha['concluida'],
            'criadaEm' => (new DateTimeImmutable($linha['criada_em']))->format(DATE_ATOM),
        ];
    }
}
