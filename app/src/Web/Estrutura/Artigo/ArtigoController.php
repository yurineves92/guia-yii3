<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Artigo;

use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Body;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class ArtigoController
{
    private const ARTIGOS = [
        'actions' => 'Actions e controllers',
        'middleware' => 'Middleware',
        'views' => 'Views',
    ];

    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ResponseFactoryInterface $responseFactory,
        private NotFoundHandler $notFoundHandler,
    ) {}

    public function listar(#[Query('busca')] string $busca = ''): ResponseInterface
    {
        $artigos = array_filter(
            self::ARTIGOS,
            static fn(string $titulo): bool => $busca === '' || mb_stripos($titulo, $busca) !== false,
        );

        return $this->viewRenderer->render(__DIR__ . '/listar', [
            'artigos' => $artigos,
            'busca' => $busca,
        ]);
    }

    public function ver(ServerRequestInterface $request, #[RouteArgument('slug')] string $slug): ResponseInterface
    {
        if (!isset(self::ARTIGOS[$slug])) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/ver', [
            'titulo' => self::ARTIGOS[$slug],
        ]);
    }

    public function comentar(
        #[RouteArgument('slug')] string $slug,
        #[Body('texto')] string $texto = '',
    ): ResponseInterface {
        $response = $this->responseFactory
            ->createResponse(Status::CREATED)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $response->getBody()->write(sprintf(
            "Comentário com %d caracteres recebido para \"%s\".\n",
            mb_strlen($texto),
            $slug,
        ));

        return $response;
    }
}
