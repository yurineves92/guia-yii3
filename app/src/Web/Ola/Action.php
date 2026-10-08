<?php

declare(strict_types=1);

namespace App\Web\Ola;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(#[Query('mensagem')] ?string $mensagem = null): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'mensagem' => $mensagem ?? 'Olá',
        ]);
    }
}
