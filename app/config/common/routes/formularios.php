<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Http\Method;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/formularios')
        ->routes(
            Route::methods([Method::GET, Method::POST], '/contato')
                ->action(Web\Formularios\Contato\Action::class)
                ->name('formularios/contato'),
            Route::methods([Method::GET, Method::POST], '/inscricao')
                ->action(Web\Formularios\Inscricao\Action::class)
                ->name('formularios/inscricao'),
            Route::methods([Method::GET, Method::POST], '/upload')
                ->action(Web\Formularios\Upload\Action::class)
                ->name('formularios/upload'),
            Route::methods([Method::GET, Method::POST], '/pedido')
                ->action(Web\Formularios\Pedido\Action::class)
                ->name('formularios/pedido'),
        ),
];
