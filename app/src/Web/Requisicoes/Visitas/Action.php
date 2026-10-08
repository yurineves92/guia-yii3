<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Visitas;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Session\SessionInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private SessionInterface $session,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $visitas = (int) $this->session->get('requisicoes.visitas', 0) + 1;
        $this->session->set('requisicoes.visitas', $visitas);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Você abriu esta página $visitas vez(es) nesta sessão.\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
