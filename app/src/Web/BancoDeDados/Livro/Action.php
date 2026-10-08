<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Livro;

use App\BancoDeDados\Livro;
use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private NotFoundHandler $notFoundHandler,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[RouteArgument('id')] int $id): ResponseInterface
    {
        $livro = Livro::query()->findByPk($id);

        if ($livro === null) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/template', ['livro' => $livro]);
    }
}
