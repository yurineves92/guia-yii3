<?php

declare(strict_types=1);

use App\Requisicoes\ArtigoNaoEncontradoException;
use App\Web\NotFound\NotFoundHandler;
use App\Web\Requisicoes;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\DataResponse\Formatter\JsonFormatter;
use Yiisoft\DataResponse\Formatter\XmlFormatter;
use Yiisoft\DataResponse\Middleware\ContentNegotiatorDataResponseMiddleware;
use Yiisoft\ErrorHandler\Middleware\ExceptionResponder;
use Yiisoft\Http\Method;
use Yiisoft\Request\Body\RequestBodyParser;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Yii\Middleware\Redirect;

return [
    Group::create('/requisicoes')
        ->namePrefix('requisicoes/')
        ->routes(
            Route::get('/artigos/{id:\d+}')
                ->action(Requisicoes\Artigo\Action::class)
                ->name('artigo'),
            Route::get('/arquivo[/{ano:\d{4}}[/{mes:\d{1,2}}]]')
                ->action(Requisicoes\Arquivo\Action::class)
                ->defaults(['ano' => 2026])
                ->name('arquivo'),
            Route::get('/links')
                ->action(Requisicoes\Links\Action::class)
                ->name('links'),
            Route::methods([Method::GET, Method::POST], '/inspecao')
                ->middleware(RequestBodyParser::class)
                ->action(Requisicoes\Inspecao\Action::class)
                ->name('inspecao'),
            Route::get('/busca')
                ->action(Requisicoes\Busca\Action::class)
                ->name('busca'),
            Route::post('/contato')
                ->middleware(RequestBodyParser::class)
                ->action(Requisicoes\Contato\Action::class)
                ->name('contato'),
            Route::post('/artigos')
                ->action(Requisicoes\Criar\Action::class)
                ->name('criar'),
            Route::get('/redirecionar')
                ->action(Requisicoes\Redirecionar\Action::class)
                ->name('redirecionar'),
            Route::get('/endereco-antigo')
                ->action(static fn(Redirect $redirect) => $redirect->toRoute('requisicoes/links'))
                ->name('endereco-antigo'),
            Route::get('/json')
                ->action(Requisicoes\Json\Action::class)
                ->name('json'),
            Route::get('/download')
                ->action(Requisicoes\Download\Action::class)
                ->name('download'),
            Route::get('/dados')
                ->middleware([
                    'class' => ContentNegotiatorDataResponseMiddleware::class,
                    '__construct()' => [
                        'formatters' => [
                            'application/json' => new JsonFormatter(),
                            'application/xml' => new XmlFormatter(),
                        ],
                        'fallback' => new JsonFormatter(),
                    ],
                ])
                ->action(Requisicoes\Dados\Action::class)
                ->name('dados'),
            Route::get('/visitas')
                ->action(Requisicoes\Visitas\Action::class)
                ->name('visitas'),
            Route::methods([Method::GET, Method::POST], '/recado')
                ->action(Requisicoes\Recado\Action::class)
                ->name('recado'),
            Route::get('/tema')
                ->action(Requisicoes\Tema\Action::class)
                ->name('tema'),
            Route::get('/erro')
                ->action(Requisicoes\Erro\Action::class)
                ->name('erro'),
            Route::get('/publicados/{id:\d+}')
                ->middleware([
                    'class' => ExceptionResponder::class,
                    '__construct()' => [
                        'exceptionMap' => [
                            ArtigoNaoEncontradoException::class => static fn(
                                NotFoundHandler $notFoundHandler,
                                ServerRequestInterface $request,
                            ) => $notFoundHandler->handle($request),
                        ],
                    ],
                ])
                ->action(Requisicoes\Publicado\Action::class)
                ->name('publicado'),
            Route::get('/pagamento')
                ->action(Requisicoes\Pagamento\Action::class)
                ->name('pagamento'),
        ),
];
