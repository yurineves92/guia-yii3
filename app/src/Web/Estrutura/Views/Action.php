<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Views;

use App\Web\Estrutura\Shared\Layout\SecaoInjection;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    private WebViewRenderer $viewRenderer;

    public function __construct(WebViewRenderer $viewRenderer)
    {
        $this->viewRenderer = $viewRenderer
            ->withLayout('@src/Web/Estrutura/Shared/Layout/layout.php')
            ->withAddedInjections(SecaoInjection::class);
    }

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'capitulos' => [
                ['titulo' => 'Visão geral', 'palavras' => 900],
                ['titulo' => 'Views', 'palavras' => 1400],
                ['titulo' => 'Widgets', 'palavras' => 1100],
            ],
        ]);
    }
}
