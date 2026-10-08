<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Busca;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(
        #[Query('q')] string $termo = '',
        #[Query] int $pagina = 1,
        #[Query('tag')] array $tags = [],
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(sprintf(
            "Termo: %s\nPágina: %d\nTags: %s\n",
            $termo,
            $pagina,
            implode(', ', $tags),
        ));

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
