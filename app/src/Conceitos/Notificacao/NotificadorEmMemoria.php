<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

final class NotificadorEmMemoria implements NotificadorInterface
{
    /** @var list<string> */
    private array $enviadas = [];

    public function enviar(string $para, string $mensagem): void
    {
        $this->enviadas[] = "$para: $mensagem";
    }

    /**
     * @return list<string>
     */
    public function enviadas(): array
    {
        return $this->enviadas;
    }
}
