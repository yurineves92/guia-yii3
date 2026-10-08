<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Links;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $links = [
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42]),
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42], ['aba' => 'comentarios']),
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42, 'destaque' => 1]),
            $this->urlGenerator->generate('requisicoes/artigo', ['id' => 42], hash: 'fim'),
            $this->urlGenerator->generate('requisicoes/arquivo'),
            $this->urlGenerator->generate('requisicoes/arquivo', ['ano' => 2024, 'mes' => 3]),
            $this->urlGenerator->generateAbsolute('requisicoes/artigo', ['id' => 42]),
        ];

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(implode("\n", $links) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
