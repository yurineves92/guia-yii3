<?php

declare(strict_types=1);

namespace App\Web\Api\V1\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Documentação dos endpoints de tarefas. Os métodos ficam vazios:
 * o código de verdade está em App\Web\Api\V1\Tarefa\TarefaController.
 */
final class TarefaEndpoints
{
    #[OA\Get(
        path: '/tarefas',
        operationId: 'listarTarefas',
        summary: 'Lista as tarefas, com paginação',
        tags: ['Tarefas'],
        parameters: [
            new OA\Parameter(name: 'pagina', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'porPagina', in: 'query', schema: new OA\Schema(type: 'integer', default: 5, maximum: 50, minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Uma página de tarefas', content: new OA\JsonContent(ref: '#/components/schemas/ListaDeTarefas')),
            new OA\Response(response: 400, ref: '#/components/responses/ParametrosInvalidos'),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
        ],
    )]
    public function index(): void {}

    #[OA\Get(
        path: '/tarefas/{id}',
        operationId: 'verTarefa',
        summary: 'Mostra uma tarefa',
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'A tarefa', content: new OA\JsonContent(ref: '#/components/schemas/Tarefa')),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
        ],
    )]
    public function view(): void {}

    #[OA\Post(
        path: '/tarefas',
        operationId: 'criarTarefa',
        summary: 'Cria uma tarefa',
        security: [['token' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TarefaInput')),
        tags: ['Tarefas'],
        responses: [
            new OA\Response(response: 201, description: 'Tarefa criada', content: new OA\JsonContent(ref: '#/components/schemas/Tarefa')),
            new OA\Response(response: 401, ref: '#/components/responses/NaoAutenticado'),
            new OA\Response(response: 422, ref: '#/components/responses/DadosInvalidos'),
        ],
    )]
    public function create(): void {}

    #[OA\Put(
        path: '/tarefas/{id}',
        operationId: 'atualizarTarefa',
        summary: 'Substitui os dados de uma tarefa',
        security: [['token' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TarefaInput')),
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 200, description: 'Tarefa atualizada', content: new OA\JsonContent(ref: '#/components/schemas/Tarefa')),
            new OA\Response(response: 401, ref: '#/components/responses/NaoAutenticado'),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
            new OA\Response(response: 422, ref: '#/components/responses/DadosInvalidos'),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/tarefas/{id}',
        operationId: 'apagarTarefa',
        summary: 'Apaga uma tarefa',
        security: [['token' => []]],
        tags: ['Tarefas'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/id')],
        responses: [
            new OA\Response(response: 204, description: 'Tarefa apagada'),
            new OA\Response(response: 401, ref: '#/components/responses/NaoAutenticado'),
            new OA\Response(response: 404, ref: '#/components/responses/NaoEncontrado'),
        ],
    )]
    public function delete(): void {}
}
