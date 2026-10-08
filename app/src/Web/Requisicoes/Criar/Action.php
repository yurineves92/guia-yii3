<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Criar;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(Status::CREATED);
        $response->getBody()->write('Artigo 7 criado.');

        return $response
            ->withHeader(Header::CONTENT_TYPE, 'text/plain; charset=utf-8')
            ->withHeader(Header::LOCATION, $this->urlGenerator->generate('requisicoes/artigo', ['id' => 7]))
            ->withHeader('X-Exemplo', 'requisicoes');
    }
}
