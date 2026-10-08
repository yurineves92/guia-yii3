<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Inspecao;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $linhas = [
            'Método: ' . $request->getMethod(),
            'Caminho: ' . $request->getUri()->getPath(),
            'Query: ' . json_encode($request->getQueryParams(), JSON_UNESCAPED_UNICODE),
            'Content-Type: ' . $request->getHeaderLine('Content-Type'),
            'User-Agent: ' . $request->getHeaderLine('User-Agent'),
            'Corpo: ' . json_encode($request->getParsedBody(), JSON_UNESCAPED_UNICODE),
        ];

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(implode("\n", $linhas) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
