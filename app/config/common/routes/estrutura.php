<?php

declare(strict_types=1);

use App\Web\Estrutura;
use App\Web\Estrutura\Artigo\ArtigoController;
use App\Web\Estrutura\Middleware\ExigeChaveMiddleware;
use App\Web\Estrutura\Middleware\TempoDeRespostaMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Route::get('/estrutura/ambiente')
        ->action(Estrutura\Ambiente\Action::class)
        ->name('estrutura/ambiente'),

    Route::get('/estrutura/contato')
        ->action(Estrutura\Contato\Action::class)
        ->name('estrutura/contato'),

    Route::get('/estrutura/artigos')
        ->action([ArtigoController::class, 'listar'])
        ->name('estrutura/artigos'),
    Route::get('/estrutura/artigos/{slug}')
        ->action([ArtigoController::class, 'ver'])
        ->name('estrutura/artigo'),
    Route::post('/estrutura/artigos/{slug}/comentarios')
        ->action([ArtigoController::class, 'comentar'])
        ->name('estrutura/artigo/comentar'),

    Route::get('/estrutura/views')
        ->action(Estrutura\Views\Action::class)
        ->name('estrutura/views'),

    Route::get('/estrutura/widgets')
        ->action(Estrutura\Widgets\Action::class)
        ->name('estrutura/widgets'),

    Route::get('/estrutura/assets')
        ->action(Estrutura\Assets\Action::class)
        ->name('estrutura/assets'),

    Route::get('/estrutura/tarefas')
        ->action(Estrutura\Tarefa\ListarAction::class)
        ->name('estrutura/tarefas'),
    Route::post('/estrutura/tarefas')
        ->action(Estrutura\Tarefa\CriarAction::class)
        ->name('estrutura/tarefa/criar'),
    Route::post('/estrutura/tarefas/{id:\d+}/concluir')
        ->action(Estrutura\Tarefa\ConcluirAction::class)
        ->name('estrutura/tarefa/concluir'),

    Group::create('/estrutura/painel')
        ->middleware(TempoDeRespostaMiddleware::class, ExigeChaveMiddleware::class)
        ->routes(
            Route::get('')
                ->action(Estrutura\Painel\Action::class)
                ->name('estrutura/painel'),
            Route::get('/status')
                ->action(Estrutura\Painel\StatusAction::class)
                ->disableMiddleware(ExigeChaveMiddleware::class)
                ->name('estrutura/painel/status'),
        ),
];
