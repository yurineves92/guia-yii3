<?php

declare(strict_types=1);

namespace App\Conceitos\Eventos;

use Psr\Log\LoggerInterface;

final readonly class RegistrarPedidoNoLog
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PedidoConfirmado $evento): void
    {
        $this->logger->info('Pedido {pedido} confirmado. Total: {total}.', [
            'pedido' => $evento->pedido,
            'total' => $evento->total,
            'category' => 'conceitos',
        ]);
    }
}
