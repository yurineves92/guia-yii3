<?php

declare(strict_types=1);

namespace App\Web\Cache\Contador;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Cache\CacheInterface;

final readonly class Action
{
    public function __construct(
        private CacheInterface $cache,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $psr = $this->cache->psr();

        $visitas = $psr->get('cache.contador', 0) + 1;
        $psr->set('cache.contador', $visitas, 60);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Visitas: $visitas\n");

        return $response;
    }
}
