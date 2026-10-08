<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

final class AvisoDePedido
{
    private string $prefixo = '';

    public function __construct(
        private readonly NotificadorInterface $notificador,
        private readonly string $remetente,
    ) {}

    public function usarPrefixo(string $prefixo): void
    {
        $this->prefixo = $prefixo . ' ';
    }

    public function avisar(string $cliente, int $pedido): string
    {
        $mensagem = "{$this->prefixo}Seu pedido $pedido foi recebido. Responda para {$this->remetente}.";
        $this->notificador->enviar($cliente, $mensagem);

        return $mensagem;
    }
}
