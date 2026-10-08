<?php

declare(strict_types=1);

namespace App\Web\Api\Eco;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory->createResponse([
            'recebido' => $request->getParsedBody(),
        ]);
    }
}
