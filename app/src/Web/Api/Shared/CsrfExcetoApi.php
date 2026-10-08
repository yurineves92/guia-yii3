<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Csrf\CsrfTokenMiddleware;

/**
 * Aplica a proteção CSRF a todo o site, menos às rotas da API.
 * A API é autenticada por token no cabeçalho Authorization, e não por cookie.
 */
final readonly class CsrfExcetoApi implements MiddlewareInterface
{
    public function __construct(
        private CsrfTokenMiddleware $csrf,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $caminho = $request->getUri()->getPath();

        if ($caminho === '/api' || str_starts_with($caminho, '/api/')) {
            return $handler->handle($request);
        }

        return $this->csrf->process($request, $handler);
    }
}
