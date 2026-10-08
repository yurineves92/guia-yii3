<?php

declare(strict_types=1);

namespace App\Web\Dados\Grade;

use App\Dados\Catalogo;
use App\Dados\Formatador;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Paginator\OffsetPaginator;
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
        $paginador = (new OffsetPaginator($this->catalogo->livros()))->withPageSize(5);

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'paginador' => $paginador,
            'formatador' => $this->formatador,
        ]);
    }
}
