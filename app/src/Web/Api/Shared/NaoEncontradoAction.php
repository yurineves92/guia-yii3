<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Responde com 404 em problem+json a qualquer caminho de /api que não tenha rota.
 */
final readonly class NaoEncontradoAction
{
    public function __construct(
        private Problemas $problemas,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemas->naoEncontrado(
            'Nenhum endpoint em ' . $request->getUri()->getPath() . '.',
        );
    }
}
