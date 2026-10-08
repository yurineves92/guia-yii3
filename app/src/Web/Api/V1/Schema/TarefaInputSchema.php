<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TarefaInput',
    required: ['titulo'],
    properties: [
        new OA\Property(property: 'titulo', type: 'string', maxLength: 200, minLength: 3, example: 'Ler a RFC 9457'),
        new OA\Property(property: 'concluida', type: 'boolean', default: false),
    ],
    type: 'object',
)]
final class TarefaInputSchema
{
}
