<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Tarefa;

use App\Estrutura\Tarefa\Tarefa;
use App\Estrutura\Tarefa\TarefaRepositoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class CriarAction
{
    public function __construct(
        private TarefaRepositoryInterface $tarefas,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(#[Body('titulo')] string $titulo = ''): ResponseInterface
    {
        $titulo = trim($titulo);

        if ($titulo === '') {
            $response = $this->responseFactory->createResponse(Status::UNPROCESSABLE_ENTITY);
            $response->getBody()->write("Informe o título da tarefa.\n");

            return $response;
        }

        $this->tarefas->salvar(new Tarefa($titulo));

        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('estrutura/tarefas'));
    }
}
