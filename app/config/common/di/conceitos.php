<?php

declare(strict_types=1);

use App\Conceitos\Notificacao\AvisoDePedido;
use App\Conceitos\Notificacao\NotificadorEmMemoria;
use App\Conceitos\Notificacao\NotificadorInterface;
use App\Conceitos\Notificacao\NotificadorPorLog;
use App\Conceitos\Notificacao\RelatorioDiario;
use App\Conceitos\Saudacao;
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\Reference;

/** @var array $params */

return [
    Saudacao::class => [
        '__construct()' => [
            'mensagem' => $params['conceitos']['saudacao'],
            'itensPorPagina' => $params['conceitos']['itensPorPagina'],
        ],
    ],

    NotificadorInterface::class => NotificadorPorLog::class,

    NotificadorPorLog::class => static fn(LoggerInterface $logger) => new NotificadorPorLog($logger, canal: 'pedidos'),

    AvisoDePedido::class => [
        '__construct()' => [
            'remetente' => $params['conceitos']['remetente'],
        ],
        'usarPrefixo()' => ['[Guia Yii3]'],
    ],

    RelatorioDiario::class => [
        '__construct()' => [
            'notificador' => Reference::to(NotificadorEmMemoria::class),
        ],
    ],

    NotificadorEmMemoria::class => [
        'class' => NotificadorEmMemoria::class,
        'reset' => function (): void {
            $this->enviadas = [];
        },
    ],
];
