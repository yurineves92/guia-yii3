<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Responde 403 com uma página de "acesso negado".
 */
final readonly class Proibido implements RequestHandlerInterface
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->viewRenderer
            ->render(__DIR__ . '/acesso-negado')
            ->withStatus(Status::FORBIDDEN);
    }
}
