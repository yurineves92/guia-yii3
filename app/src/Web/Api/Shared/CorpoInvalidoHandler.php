<?php

declare(strict_types=1);

namespace App\Web\Api\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\BadRequestHandlerInterface;
use Yiisoft\Request\Body\ParserException;

/**
 * Resposta do RequestBodyParser quando o corpo da requisição não pode ser lido.
 */
final class CorpoInvalidoHandler implements BadRequestHandlerInterface
{
    private ?ParserException $erro = null;

    public function __construct(
        private readonly Problemas $problemas,
    ) {}

    public function withParserException(ParserException $e): BadRequestHandlerInterface
    {
        $new = clone $this;
        $new->erro = $e;
        return $new;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->problemas->criar(
            Status::BAD_REQUEST,
            $this->erro?->getMessage() ?? 'O corpo da requisição é inválido.',
        );
    }
}
