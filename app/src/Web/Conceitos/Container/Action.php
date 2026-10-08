<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Container;

use App\Conceitos\Notificacao\AvisoDePedido;
use App\Conceitos\Notificacao\NotificadorEmMemoria;
use App\Conceitos\Notificacao\RelatorioDiario;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private AvisoDePedido $aviso,
        private RelatorioDiario $relatorio,
        private NotificadorEmMemoria $memoria,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $mensagem = $this->aviso->avisar('ana@exemplo.com', 1001);
        $this->relatorio->enviar('gerente@exemplo.com', 12);

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(
            "Aviso: $mensagem\n"
            . 'Em memória: ' . implode(' | ', $this->memoria->enviadas()) . "\n",
        );

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
