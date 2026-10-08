<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ListaDeTarefas',
    properties: [
        new OA\Property(property: 'dados', type: 'array', items: new OA\Items(ref: '#/components/schemas/Tarefa')),
        new OA\Property(
            property: 'paginacao',
            properties: [
                new OA\Property(property: 'pagina', type: 'integer', example: 1),
                new OA\Property(property: 'porPagina', type: 'integer', example: 5),
                new OA\Property(property: 'totalDeItens', type: 'integer', example: 8),
                new OA\Property(property: 'totalDePaginas', type: 'integer', example: 2),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
final class ListaDeTarefasSchema
{
}
