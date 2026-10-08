<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;

final readonly class ExigeChaveMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $chave,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!hash_equals($this->chave, $request->getHeaderLine('X-Chave'))) {
            $response = $this->responseFactory->createResponse(Status::FORBIDDEN);
            $response->getBody()->write("Chave ausente ou inválida.\n");

            return $response;
        }

        return $handler->handle($request);
    }
}
