<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Imutabilidade;

use App\Conceitos\Imutabilidade\Filtro;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $base = new Filtro();
        $busca = $base->withTermo('yii')->withLimite(5);

        $response = $this->responseFactory->createResponse();
        $response->withHeader('X-Esquecido', 'sim');
        $response = $response->withHeader('X-Guardado', 'sim');

        $response->getBody()->write(
            'Filtro base: ' . $base->descrever() . "\n"
            . 'Filtro da busca: ' . $busca->descrever() . "\n"
            . 'Tem X-Esquecido? ' . ($response->hasHeader('X-Esquecido') ? 'sim' : 'não') . "\n"
            . 'Tem X-Guardado? ' . ($response->hasHeader('X-Guardado') ? 'sim' : 'não') . "\n",
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
