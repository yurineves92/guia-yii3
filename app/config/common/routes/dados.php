<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Router\Route;

return [
    Route::get('/dados/formatacao')
        ->action(Web\Dados\Formatacao\Action::class)
        ->name('dados/formatacao'),
    Route::get('/dados/paginacao')
        ->action(Web\Dados\Paginacao\Action::class)
        ->name('dados/paginacao'),
    Route::get('/dados/keyset')
        ->action(Web\Dados\Keyset\Action::class)
        ->name('dados/keyset'),
    Route::get('/dados/ordenacao')
        ->action(Web\Dados\Ordenacao\Action::class)
        ->name('dados/ordenacao'),
    Route::get('/dados/filtros')
        ->action(Web\Dados\Filtros\Action::class)
        ->name('dados/filtros'),
    Route::get('/dados/grade')
        ->action(Web\Dados\Grade\Action::class)
        ->name('dados/grade'),
    Route::get('/dados/lista')
        ->action(Web\Dados\Lista\Action::class)
        ->name('dados/lista'),
];
