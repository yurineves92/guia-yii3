<?php

declare(strict_types=1);

use App\Conceitos\Eventos\PedidoConfirmado;
use App\Conceitos\Eventos\RegistrarPedidoNoLog;
use App\Conceitos\Notificacao\AvisoDePedido;
use Psr\Log\LoggerInterface;
use Yiisoft\Yii\Http\Event\BeforeRequest;

return [
    PedidoConfirmado::class => [
        RegistrarPedidoNoLog::class,
        static function (PedidoConfirmado $evento, AvisoDePedido $aviso): void {
            $aviso->avisar($evento->cliente, $evento->pedido);
        },
    ],

    BeforeRequest::class => [
        static function (BeforeRequest $evento, LoggerInterface $logger): void {
            $caminho = $evento->getRequest()->getUri()->getPath();
            if (str_starts_with($caminho, '/conceitos/')) {
                $logger->debug('Requisição para {caminho}', ['caminho' => $caminho, 'category' => 'conceitos']);
            }
        },
    ],
];
