<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Http\Method;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->routes(
            Route::get('/')
                ->action(Web\HomePage\Action::class)
                ->name('home'),
            Route::get('/ola')
                ->action(Web\Ola\Action::class)
                ->name('ola'),
            Route::methods([Method::GET, Method::POST], '/cadastro')
                ->action(Web\Cadastro\Action::class)
                ->name('cadastro'),
            Route::get('/paises')
                ->action(Web\Pais\Action::class)
                ->name('pais'),
        ),
];
