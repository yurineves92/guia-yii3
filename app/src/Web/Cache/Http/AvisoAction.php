<?php

declare(strict_types=1);

namespace App\Web\Cache\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Header;

final readonly class AvisoAction
{
    public const ARQUIVO = __DIR__ . '/aviso.txt';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'text/plain; charset=utf-8');
        $response->getBody()->write(file_get_contents(self::ARQUIVO));

        return $response;
    }
}
