<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

final readonly class RelatorioDiario
{
    public function __construct(
        private NotificadorInterface $notificador,
    ) {}

    public function enviar(string $para, int $pedidos): void
    {
        $this->notificador->enviar($para, "Hoje foram $pedidos pedidos.");
    }
}
