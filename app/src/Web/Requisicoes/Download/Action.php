<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Download;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Yiisoft\Http\ContentDispositionHeader;
use Yiisoft\Http\Header;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $csv = "codigo;nome\nBR;Brasil\nPT;Portugal\n";

        return $this->responseFactory
            ->createResponse()
            ->withHeader(Header::CONTENT_TYPE, 'text/csv; charset=utf-8')
            ->withHeader(ContentDispositionHeader::name(), ContentDispositionHeader::attachment('países.csv'))
            ->withBody($this->streamFactory->createStream($csv));
    }
}
