<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Tarefa;

use App\Api\Tarefa\TarefaRepository;
use App\Web\Api\Shared\Problemas;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class TarefaController
{
    private const POR_PAGINA_MAXIMO = 50;

    public function __construct(
        private TarefaRepository $tarefas,
        private JsonResponseFactory $json,
        private ResponseFactoryInterface $responseFactory,
        private Problemas $problemas,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function index(
        #[Query('pagina')] int $pagina = 1,
        #[Query('porPagina')] int $porPagina = 5,
    ): ResponseInterface {
        if ($pagina < 1 || $porPagina < 1 || $porPagina > self::POR_PAGINA_MAXIMO) {
            return $this->problemas->criar(
                Status::BAD_REQUEST,
                'Use pagina >= 1 e porPagina entre 1 e ' . self::POR_PAGINA_MAXIMO . '.',
            );
        }

        $paginator = (new OffsetPaginator($this->tarefas->leitor()))->withPageSize($porPagina);

        if ($pagina > max(1, $paginator->getTotalPages())) {
            return $this->problemas->naoEncontrado("A página $pagina não existe.");
        }

        $paginator = $paginator->withCurrentPage($pagina);

        $dados = [];
        foreach ($paginator->read() as $linha) {
            $dados[] = TarefaResource::deLinha($linha);
        }

        return $this->json->createResponse([
            'dados' => $dados,
            'paginacao' => [
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'totalDeItens' => $paginator->getTotalItems(),
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

    public function create(TarefaInput $input): ResponseInterface
    {
        $resultado = $input->getValidationResult();
        if (!$resultado->isValid()) {
            return $this->problemas->validacao($resultado);
        }

        $id = $this->tarefas->criar($input->titulo, $input->concluida);

        return $this->json
            ->createResponse(TarefaResource::deLinha($this->tarefas->buscar($id)), Status::CREATED)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('api/v1/tarefas/view', ['id' => $id]));
    }

    public function update(#[RouteArgument('id')] int $id, TarefaInput $input): ResponseInterface
    {
        if ($this->tarefas->buscar($id) === null) {
            return $this->problemas->naoEncontrado("A tarefa $id não existe.");
        }

        $resultado = $input->getValidationResult();
        if (!$resultado->isValid()) {
            return $this->problemas->validacao($resultado);
        }

        $this->tarefas->atualizar($id, ['titulo' => $input->titulo, 'concluida' => $input->concluida]);

        return $this->json->createResponse(TarefaResource::deLinha($this->tarefas->buscar($id)));
    }

    public function delete(#[RouteArgument('id')] int $id): ResponseInterface
    {
        if (!$this->tarefas->apagar($id)) {
            return $this->problemas->naoEncontrado("A tarefa $id não existe.");
        }

        return $this->responseFactory->createResponse(Status::NO_CONTENT);
    }
}
