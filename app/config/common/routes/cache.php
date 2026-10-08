<?php

declare(strict_types=1);

use App\Web\Cache;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Yii\Middleware\HttpCache;

return [
    Group::create('/cache')
        ->namePrefix('cache/')
        ->routes(
            Route::get('/contador')
                ->action(Cache\Contador\Action::class)
                ->name('contador'),
            Route::get('/paises')
                ->action(Cache\Paises\Action::class)
                ->name('paises'),
            Route::post('/paises/limpar')
                ->action(Cache\Paises\LimparAction::class)
                ->name('paises/limpar'),
            Route::get('/http/paises/{codigo:[A-Z]{2}}')
                ->action(Cache\Http\PaisAction::class)
                ->name('http/pais'),
            Route::get('/http/aviso')
                ->middleware(
                    static fn () => (new HttpCache())
                        ->withLastModified(static fn () => filemtime(Cache\Http\AvisoAction::ARQUIVO))
                        ->withCacheControlHeader('public, max-age=300'),
                )
                ->action(Cache\Http\AvisoAction::class)
                ->name('http/aviso'),
        ),
];
