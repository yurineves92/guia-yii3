<?php

declare(strict_types=1);

namespace App\Web\Api\V2\Tarefa;

use DateTimeImmutable;

/**
 * Formato da versão 2: `concluida` (booleano) virou `status` (texto).
 */
final class TarefaResource
{
    /**
     * @return array{id: int, titulo: string, status: string, criadaEm: string}
     */
    public static function deLinha(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'titulo' => (string) $linha['titulo'],
            'status' => $linha['concluida'] ? 'concluida' : 'pendente',
            'criadaEm' => (new DateTimeImmutable($linha['criada_em']))->format(DATE_ATOM),
        ];
    }
}
