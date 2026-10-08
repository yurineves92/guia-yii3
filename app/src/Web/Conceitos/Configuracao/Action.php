<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Configuracao;

use App\Conceitos\Saudacao;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Saudacao $saudacao,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($this->saudacao->texto() . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
