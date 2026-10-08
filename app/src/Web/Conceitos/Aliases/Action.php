<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Aliases;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Aliases\Aliases;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Aliases $aliases,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $linhas = [
            '@root => ' . $this->aliases->get('@root'),
            '@runtime => ' . $this->aliases->get('@runtime'),
            '@relatorios/vendas.csv => ' . $this->aliases->get('@relatorios/vendas.csv'),
            '@assetsUrl => ' . $this->aliases->get('@assetsUrl'),
            'caminho sem alias => ' . $this->aliases->get('runtime/tmp'),
        ];

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(implode("\n", $linhas) . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
