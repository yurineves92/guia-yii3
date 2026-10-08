<?php

declare(strict_types=1);

namespace App\Web\Api\Documentacao;

use OpenApi\Generator;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Http\Header;

/**
 * Gera a especificação OpenAPI a partir dos atributos #[OA\...] da versão 1.
 */
final readonly class EspecificacaoAction
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Aliases $aliases,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $openApi = (new Generator($this->logger))
            ->setVersion('3.1.0')
            ->generate([
                $this->aliases->get('@src/Web/Api/V1/OpenApi'),
                $this->aliases->get('@src/Web/Api/V1/Schema'),
            ]);

        $response = $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'application/json; charset=utf-8');
        $response->getBody()->write($openApi->toJson());

        return $response;
    }
}
