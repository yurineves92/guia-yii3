<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Artigo;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(#[RouteArgument('id')] int $id): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Artigo número $id");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
