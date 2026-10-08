<?php

declare(strict_types=1);

use App\Api\Token\TokenRepository;
use App\Web\Api;
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\Auth\Method\HttpBearer;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\Definitions\Reference;
use Yiisoft\Http\Method;
use Yiisoft\Request\Body\RequestBodyParser;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/api')
        ->namePrefix('api/')
        ->middleware(
            Api\Shared\ErroInternoMiddleware::class,
            [
                'class' => RequestBodyParser::class,
                '__construct()' => [
                    'badRequestHandler' => Reference::to(Api\Shared\CorpoInvalidoHandler::class),
                ],
            ],
        )
        ->routes(
            Route::get('/ping')
                ->action(Api\Ping\Action::class)
                ->name('ping'),
            Route::post('/eco')
                ->action(Api\Eco\Action::class)
                ->name('eco'),
            Route::get('/erro')
                ->action(Api\Erro\Action::class)
                ->name('erro'),
            Route::get('/docs')
                ->action(Api\Documentacao\SwaggerUiAction::class)
                ->name('docs'),
            Group::create('/v1')
                ->namePrefix('v1/')
                ->routes(
                    Route::get('/openapi.json')
                        ->action(Api\Documentacao\EspecificacaoAction::class)
                        ->name('openapi'),
                    Route::get('/tarefas')
                        ->action([Api\V1\Tarefa\TarefaController::class, 'index'])
                        ->name('tarefas/index'),
                    Route::get('/tarefas/{id:\d+}')
                        ->action([Api\V1\Tarefa\TarefaController::class, 'view'])
                        ->name('tarefas/view'),
                    Group::create()
                        ->middleware(
                            static fn (
                                TokenRepository $tokens,
                                ResponseFactoryInterface $responseFactory,
                                Api\Shared\FalhaDeAutenticacao $falha,
                            ) => new Authentication(new HttpBearer($tokens), $responseFactory, $falha),
                        )
                        ->routes(
                            Route::get('/eu')
                                ->action(Api\V1\Eu\Action::class)
                                ->name('eu'),
                            Route::post('/tarefas')
                                ->action([Api\V1\Tarefa\TarefaController::class, 'create'])
                                ->name('tarefas/create'),
                            Route::put('/tarefas/{id:\d+}')
                                ->action([Api\V1\Tarefa\TarefaController::class, 'update'])
                                ->name('tarefas/update'),
                            Route::delete('/tarefas/{id:\d+}')
                                ->action([Api\V1\Tarefa\TarefaController::class, 'delete'])
                                ->name('tarefas/delete'),
                        ),
                ),
            Group::create('/v2')
                ->namePrefix('v2/')
                ->routes(
                    Route::get('/tarefas')
                        ->action([Api\V2\Tarefa\TarefaController::class, 'index'])
                        ->name('tarefas/index'),
                    Route::get('/tarefas/{id:\d+}')
                        ->action([Api\V2\Tarefa\TarefaController::class, 'view'])
                        ->name('tarefas/view'),
                ),
            Route::methods([Method::GET, Method::POST, Method::PUT, Method::PATCH, Method::DELETE], '/{caminho:.+}')
                ->action(Api\Shared\NaoEncontradoAction::class),
        ),
];
