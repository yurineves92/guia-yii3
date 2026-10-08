<?php

declare(strict_types=1);

namespace App\Conceitos\Eventos;

final readonly class PedidoConfirmado
{
    public function __construct(
        public int $pedido,
        public string $cliente,
        public float $total,
    ) {}
}
