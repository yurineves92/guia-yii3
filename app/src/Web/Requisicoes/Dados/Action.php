<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Dados;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;

final readonly class Action
{
    public function __construct(
        private DataResponseFactoryInterface $dataResponseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->dataResponseFactory->createResponse([
            'id' => 42,
            'titulo' => 'Configuração no Yii3',
        ]);
    }
}
