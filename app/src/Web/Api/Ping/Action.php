<?php

declare(strict_types=1);

namespace App\Web\Api\Ping;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->responseFactory->createResponse([
            'status' => 'ok',
            'hora' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
