<?php

declare(strict_types=1);

namespace App\Web\Cache\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\ResponseFactory\JsonResponseFactory;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Router\HydratorAttribute\RouteArgument;

final readonly class PaisAction
{
    public function __construct(
        private ConnectionInterface $db,
        private ResponseFactoryInterface $responseFactory,
        private JsonResponseFactory $jsonResponseFactory,
    ) {}

    public function __invoke(
        ServerRequestInterface $request,
        #[RouteArgument('codigo')] string $codigo,
    ): ResponseInterface {
        $pais = $this->db->select(['codigo', 'nome', 'populacao'])
            ->from('pais')
            ->where(['codigo' => $codigo])
            ->one();

        if ($pais === null) {
            return $this->responseFactory->createResponse(Status::NOT_FOUND);
        }

        $pais['populacao'] = (int) $pais['populacao'];

        $etag = '"' . md5(serialize($pais)) . '"';

        if ($request->getHeaderLine(Header::IF_NONE_MATCH) === $etag) {
            $response = $this->responseFactory->createResponse(Status::NOT_MODIFIED);
        } else {
            $response = $this->jsonResponseFactory->createResponse($pais);
        }

        return $response
            ->withHeader(Header::ETAG, $etag)
            ->withHeader(Header::CACHE_CONTROL, 'no-cache');
    }
}
