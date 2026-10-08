<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\Formatter\JsonFormatter;
use Yiisoft\DataResponse\ResponseFactory\FormattedResponseFactory;
use Yiisoft\Http\Status;
use Yiisoft\Validator\Result;

/**
 * Respostas de erro no formato Problem Details (RFC 9457).
 */
final readonly class Problemas
{
    private FormattedResponseFactory $factory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->factory = new FormattedResponseFactory(
            $responseFactory,
            new JsonFormatter(contentType: 'application/problem+json'),
        );
    }

    /**
     * @param array<string, mixed> $extras Campos adicionais do problema.
     */
    public function criar(int $status, string $detalhe, array $extras = []): ResponseInterface
    {
        return $this->factory->createResponse(
            [
                'type' => 'about:blank',
                'title' => Status::TEXTS[$status] ?? 'Error',
                'status' => $status,
                'detail' => $detalhe,
                ...$extras,
            ],
            $status,
        );
    }

    public function naoEncontrado(string $detalhe = 'Recurso não encontrado.'): ResponseInterface
    {
        return $this->criar(Status::NOT_FOUND, $detalhe);
    }

    public function validacao(Result $resultado): ResponseInterface
    {
        return $this->criar(
            Status::UNPROCESSABLE_ENTITY,
            'Os dados enviados são inválidos.',
            ['errors' => $resultado->getErrorMessagesIndexedByPath()],
        );
    }
}
