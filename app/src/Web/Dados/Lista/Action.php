<?php

declare(strict_types=1);

namespace App\Web\Dados\Lista;

use App\Dados\Catalogo;
use App\Dados\Formatador;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private Catalogo $catalogo,
        private Formatador $formatador,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/template', [
            'livros' => $this->catalogo->livros(),
            'formatador' => $this->formatador,
        ]);
    }
}
