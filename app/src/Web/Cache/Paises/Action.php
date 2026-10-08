<?php

declare(strict_types=1);

namespace App\Web\Cache\Paises;

use App\Cache\ResumoDePaises;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ResumoDePaises $resumo,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'resumo' => $this->resumo->obter(),
        ]);
    }
}
