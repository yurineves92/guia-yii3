<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TempoDeRespostaMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $inicio = microtime(true);

        $response = $handler->handle($request);

        $milissegundos = (microtime(true) - $inicio) * 1000;

        return $response->withHeader('X-Tempo-Resposta', sprintf('%.2f ms', $milissegundos));
    }
}
