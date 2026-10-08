<?php

declare(strict_types=1);

namespace App\Conceitos\Notificacao;

use Psr\Log\LoggerInterface;

final readonly class NotificadorPorLog implements NotificadorInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private string $canal = 'geral',
    ) {}

    public function enviar(string $para, string $mensagem): void
    {
        $this->logger->info('[{canal}] Para {para}: {mensagem}', [
            'canal' => $this->canal,
            'para' => $para,
            'mensagem' => $mensagem,
            'category' => 'conceitos',
        ]);
    }
}
