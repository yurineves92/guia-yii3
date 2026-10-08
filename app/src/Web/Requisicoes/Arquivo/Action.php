<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Arquivo;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\CurrentRoute;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(CurrentRoute $currentRoute): ResponseInterface
    {
        $ano = $currentRoute->getArgument('ano');
        $mes = $currentRoute->getArgument('mes', 'todos');

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(
            "Rota: {$currentRoute->getName()}\n"
            . "Ano: $ano\n"
            . "Mês: $mes\n",
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
