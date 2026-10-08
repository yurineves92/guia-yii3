<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Tarefa;

use App\Estrutura\Tarefa\ConcluirTarefa;
use App\Estrutura\Tarefa\TarefaJaConcluidaException;
use App\Estrutura\Tarefa\TarefaNaoEncontradaException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class ConcluirAction
{
    public function __construct(
        private ConcluirTarefa $concluirTarefa,
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(#[RouteArgument('id')] int $id): ResponseInterface
    {
        try {
            $this->concluirTarefa->executar($id);
        } catch (TarefaNaoEncontradaException $e) {
            return $this->erro(Status::NOT_FOUND, $e->getMessage());
        } catch (TarefaJaConcluidaException $e) {
            return $this->erro(Status::CONFLICT, $e->getMessage());
        }

        return $this->responseFactory
            ->createResponse(Status::FOUND)
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('estrutura/tarefas'));
    }

    private function erro(int $status, string $mensagem): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($mensagem . "\n");

        return $response;
    }
}
