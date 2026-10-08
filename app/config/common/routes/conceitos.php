<?php

declare(strict_types=1);

use App\Web\Conceitos;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/conceitos')
        ->namePrefix('conceitos/')
        ->routes(
            Route::get('/configuracao')
                ->action(Conceitos\Configuracao\Action::class)
                ->name('configuracao'),
            Route::get('/container')
                ->action(Conceitos\Container\Action::class)
                ->name('container'),
            Route::get('/aliases')
                ->action(Conceitos\Aliases\Action::class)
                ->name('aliases'),
            Route::get('/pedido/{id:\d+}')
                ->action(Conceitos\Pedido\Action::class)
                ->name('pedido'),
            Route::get('/imutabilidade')
                ->action(Conceitos\Imutabilidade\Action::class)
                ->name('imutabilidade'),
            Route::get('/estado')
                ->action(Conceitos\Estado\Action::class)
                ->name('estado'),
        ),
];
