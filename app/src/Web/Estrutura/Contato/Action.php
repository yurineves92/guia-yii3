<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Contato;

use App\Estrutura\LojaParams;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LojaParams $loja,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $response->getBody()->write("Fale com a {$this->loja->nome}: {$this->loja->email}\n");

        return $response;
    }
}
