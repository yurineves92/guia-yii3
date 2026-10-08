<?php

declare(strict_types=1);

use App\Web\Topicos;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/topicos')
        ->namePrefix('topicos/')
        ->routes(
            Route::get('/i18n')
                ->action(Topicos\I18n\Action::class)
                ->name('i18n'),
        ),
];
