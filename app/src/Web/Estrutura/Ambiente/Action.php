<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Ambiente;

use App\Environment;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $response->getBody()->write(sprintf(
            "APP_ENV: %s\nAPP_DEBUG: %s\n",
            Environment::appEnv(),
            Environment::appDebug() ? 'true' : 'false',
        ));

        return $response;
    }
}
