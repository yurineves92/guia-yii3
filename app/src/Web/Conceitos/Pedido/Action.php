<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Pedido;

use App\Conceitos\Eventos\ConfirmacaoDePedido;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private ConfirmacaoDePedido $confirmacao,
    ) {}

    public function __invoke(#[RouteArgument] int $id): ResponseInterface
    {
        $this->confirmacao->confirmar($id, 'ana@exemplo.com', 149.90);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Pedido $id confirmado.\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
