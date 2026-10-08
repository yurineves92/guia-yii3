<?php

declare(strict_types=1);

namespace App\Web\Api\V2\Tarefa;

use App\Api\Tarefa\TarefaRepository;
use App\Web\Api\Shared\Problemas;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class TarefaController
{
    private const POR_PAGINA = 5;

    public function __construct(
        private TarefaRepository $tarefas,
        private JsonResponseFactory $json,
        private Problemas $problemas,
    ) {}

    public function index(#[Query('pagina')] int $pagina = 1): ResponseInterface
    {
        $paginator = (new OffsetPaginator($this->tarefas->leitor()))->withPageSize(self::POR_PAGINA);

        if ($pagina < 1 || $pagina > max(1, $paginator->getTotalPages())) {
            return $this->problemas->naoEncontrado("A página $pagina não existe.");
        }

        $dados = [];
        foreach ($paginator->withCurrentPage($pagina)->read() as $linha) {
            $dados[] = TarefaResource::deLinha($linha);
        }

        return $this->json->createResponse([
            'dados' => $dados,
            'paginacao' => [
                'pagina' => $pagina,
                'totalDePaginas' => $paginator->getTotalPages(),
            ],
        ]);
    }

    public function view(#[RouteArgument('id')] int $id): ResponseInterface
    {
        $linha = $this->tarefas->buscar($id);

        if ($linha === null) {
            return $this->problemas->naoEncontrado("A tarefa $id não existe.");
        }

        return $this->json->createResponse(TarefaResource::deLinha($linha));
    }
}
