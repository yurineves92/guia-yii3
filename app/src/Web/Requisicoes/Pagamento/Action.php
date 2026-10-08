<?php

declare(strict_types=1);

namespace App\Web\Requisicoes\Pagamento;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;

final readonly class Action
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(#[Query] int $pedido = 0, #[Query] float $valor = 0.0): ResponseInterface
    {
        $this->logger->debug('Iniciando pagamento do pedido {pedido}.', ['pedido' => $pedido]);

        if ($valor <= 0) {
            $this->logger->warning('Pedido {pedido} com valor inválido: {valor}.', [
                'pedido' => $pedido,
                'valor' => $valor,
                'category' => 'pagamentos',
            ]);

            return $this->texto('Valor inválido.');
        }

        $this->logger->info('Pedido {pedido} pago: R$ {valor}.', [
            'pedido' => $pedido,
            'valor' => number_format($valor, 2, ',', '.'),
            'category' => 'pagamentos',
        ]);

        return $this->texto('Pagamento registrado.');
    }

    private function texto(string $conteudo): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($conteudo . "\n");

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
