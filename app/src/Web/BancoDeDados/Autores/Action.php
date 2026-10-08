<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Autores;

use App\BancoDeDados\Autor;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $autores = Autor::query()
            ->with('livros')
            ->orderBy(['nome' => SORT_ASC])
            ->all();

        return $this->viewRenderer->render(__DIR__ . '/template', ['autores' => $autores]);
    }
}
