<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Publicado;

use App\Requisicoes\ArtigoNaoEncontradoException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        if ($id !== 1) {
            throw new ArtigoNaoEncontradoException($id);
        }

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Artigo $id: Olá, Yii3\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
