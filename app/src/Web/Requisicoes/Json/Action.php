<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Json;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;

final readonly class Action
{
    public function __construct(
        private JsonResponseFactory $jsonResponseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->jsonResponseFactory->createResponse([
            'id' => 42,
            'titulo' => 'Configuração no Yii3',
            'tags' => ['yii3', 'config'],
            'publicado' => true,
        ]);
    }
}
