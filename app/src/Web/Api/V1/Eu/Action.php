<?php

declare(strict_types=1);

namespace App\Web\Api\V1\Eu;

use App\Api\Token\ClienteDaApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $responseFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ClienteDaApi $cliente */
        $cliente = $request->getAttribute(Authentication::class);

        return $this->responseFactory->createResponse([
            'id' => $cliente->id,
            'cliente' => $cliente->nome,
        ]);
    }
}
