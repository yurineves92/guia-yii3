<?php

declare(strict_types=1);

use App\Web;
use Yiisoft\Router\Route;

return [
    Route::get('/banco-de-dados/comandos')
        ->action(Web\BancoDeDados\Comandos\Action::class)
        ->name('banco-de-dados/comandos'),
    Route::get('/banco-de-dados/transacao')
        ->action(Web\BancoDeDados\Transacao\Action::class)
        ->name('banco-de-dados/transacao'),
    Route::get('/banco-de-dados/livros')
        ->action(Web\BancoDeDados\Livros\Action::class)
        ->name('banco-de-dados/livros'),
    Route::get('/banco-de-dados/livros/{id:\d+}')
        ->action(Web\BancoDeDados\Livro\Action::class)
        ->name('banco-de-dados/livro'),
    Route::get('/banco-de-dados/autores')
        ->action(Web\BancoDeDados\Autores\Action::class)
        ->name('banco-de-dados/autores'),
];
