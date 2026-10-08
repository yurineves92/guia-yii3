<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

interface NotificadorInterface
{
    public function enviar(string $para, string $mensagem): void;
}
