<?php

declare(strict_types=1);

namespace App\Conceitos\Eventos;

use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class ConfirmacaoDePedido
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function confirmar(int $pedido, string $cliente, float $total): void
    {
        // Aqui entraria a regra de negócio: validar estoque, gravar no banco...

        $this->eventDispatcher->dispatch(new PedidoConfirmado($pedido, $cliente, $total));
    }
}
