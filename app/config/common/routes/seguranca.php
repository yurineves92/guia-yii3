<?php

declare(strict_types=1);

use App\Seguranca\Rbac\Permissao;
use App\Web;
use App\Web\Seguranca\Shared\CabecalhosDeSeguranca;
use App\Web\Seguranca\Shared\ExigePermissao;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\Definitions\Reference;
use Yiisoft\Http\Method;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\User\Method\WebAuth;

return [
    Group::create('/seguranca')
        ->middleware(CabecalhosDeSeguranca::class)
        ->routes(
            Route::get('/busca')
                ->action(Web\Seguranca\Busca\Action::class)
                ->name('seguranca/busca'),
            Route::methods([Method::GET, Method::POST], '/login')
                ->action(Web\Seguranca\Login\Action::class)
                ->name('seguranca/login'),
            Route::get('/convite')
                ->action(Web\Seguranca\Convite\AbrirAction::class)
                ->name('seguranca/convite'),

            // Daqui para baixo, só com login
            Group::create()
                ->middleware([
                    'class' => Authentication::class,
                    '__construct()' => ['authenticationMethod' => Reference::to(WebAuth::class)],
                ])
                ->routes(
                    Route::get('/painel')
                        ->action(Web\Seguranca\Painel\Action::class)
                        ->name('seguranca/painel'),
                    Route::post('/logout')
                        ->action(Web\Seguranca\Logout\Action::class)
                        ->name('seguranca/logout'),
                    Route::methods([Method::GET, Method::POST], '/senha')
                        ->action(Web\Seguranca\Senha\Action::class)
                        ->name('seguranca/senha'),
                    Route::methods([Method::GET, Method::POST], '/perfil/{id:\d+}')
                        ->action(Web\Seguranca\Perfil\Action::class)
                        ->name('seguranca/perfil'),
                    Route::get('/relatorios')
                        ->middleware(ExigePermissao::para(Permissao::VerRelatorios))
                        ->action(Web\Seguranca\Relatorios\Action::class)
                        ->name('seguranca/relatorios'),
                    Route::get('/convite/novo')
                        ->action(Web\Seguranca\Convite\GerarAction::class)
                        ->name('seguranca/convite/novo'),
                ),
        ),
];
