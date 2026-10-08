<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Yiisoft\Http\Status;

/**
 * Transforma qualquer exceção não tratada numa resposta 500 em problem+json.
 *
 * Os detalhes vão para o log, nunca para o cliente.
 */
final readonly class ErroInternoMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Problemas $problemas,
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return $this->problemas->criar(
                Status::INTERNAL_SERVER_ERROR,
                'Ocorreu um erro inesperado. Tente de novo mais tarde.',
            );
        }
    }
}
