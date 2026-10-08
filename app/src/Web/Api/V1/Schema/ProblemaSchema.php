<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Problema',
    description: 'Erro no formato da RFC 9457',
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'about:blank'),
        new OA\Property(property: 'title', type: 'string', example: 'Not Found'),
        new OA\Property(property: 'status', type: 'integer', example: 404),
        new OA\Property(property: 'detail', type: 'string', example: 'A tarefa 99 não existe.'),
        new OA\Property(
            property: 'errors',
            description: 'Mensagens de validação por campo (só no 422)',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
    type: 'object',
)]
final class ProblemaSchema
{
}
