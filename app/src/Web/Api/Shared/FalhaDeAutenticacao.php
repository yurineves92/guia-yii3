<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;

/**
 * Resposta do middleware de autenticação quando não há token válido.
 */
final readonly class FalhaDeAutenticacao implements RequestHandlerInterface
{
    public function __construct(
        private Problemas $problemas,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemas->criar(
            Status::UNAUTHORIZED,
            'Envie um token válido no cabeçalho Authorization: Bearer <token>.',
        );
    }
}
