<?php

declare(strict_types=1);

namespace App\Web\Conceitos\Estado;

use App\Conceitos\Notificacao\NotificadorEmMemoria;
use App\Conceitos\Notificacao\RelatorioDiario;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Di\StateResetter;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private RelatorioDiario $relatorio,
        private NotificadorEmMemoria $memoria,
        private StateResetter $stateResetter,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $this->relatorio->enviar('gerente@exemplo.com', 12);
        $this->relatorio->enviar('diretoria@exemplo.com', 12);
        $antes = count($this->memoria->enviadas());

        $this->stateResetter->reset();
        $depois = count($this->memoria->enviadas());

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write("Mensagens guardadas antes do reset: $antes\nDepois do reset: $depois\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
