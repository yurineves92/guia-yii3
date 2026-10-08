<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Tarefa',
    required: ['id', 'titulo', 'concluida', 'criadaEm'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'titulo', type: 'string', example: 'Responder os e-mails'),
        new OA\Property(property: 'concluida', type: 'boolean', example: false),
        new OA\Property(property: 'criadaEm', type: 'string', format: 'date-time', example: '2026-10-02T08:15:00-03:00'),
    ],
    type: 'object',
)]
final class TarefaSchema
{
}
