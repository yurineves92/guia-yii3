<?php

declare(strict_types=1);

namespace App\Web\Api\V1\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0.0',
        description: 'API de tarefas do guia Yii3. Erros seguem a RFC 9457 (application/problem+json).',
        title: 'API de tarefas',
    ),
    servers: [new OA\Server(url: '/api/v1', description: 'Este servidor')],
    tags: [new OA\Tag(name: 'Tarefas', description: 'Cadastro de tarefas')],
)]
#[OA\SecurityScheme(
    securityScheme: 'token',
    type: 'http',
    description: 'Token enviado em Authorization: Bearer <token>',
    scheme: 'bearer',
)]
#[OA\Response(
    response: 'ParametrosInvalidos',
    description: 'Parâmetros da requisição fora do permitido',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Response(
    response: 'NaoEncontrado',
    description: 'Recurso não encontrado',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Response(
    response: 'NaoAutenticado',
    description: 'Token ausente, inválido ou expirado',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Response(
    response: 'DadosInvalidos',
    description: 'Os dados enviados não passaram na validação',
    content: new OA\MediaType(
        mediaType: 'application/problem+json',
        schema: new OA\Schema(ref: '#/components/schemas/Problema'),
    ),
)]
#[OA\Parameter(
    parameter: 'id',
    name: 'id',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', minimum: 1),
)]
final class Especificacao
{
}
