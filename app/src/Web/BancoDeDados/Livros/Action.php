<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Livros;

use App\BancoDeDados\LivroRepository;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private LivroRepository $livros,
    ) {}

    public function __invoke(
        #[Query('busca')] ?string $busca = null,
        #[Query('preco_max')] ?string $precoMaximo = null,
    ): ResponseInterface {
        $preco = is_numeric($precoMaximo) ? (float) $precoMaximo : null;

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'busca' => $busca,
            'precoMaximo' => $preco,
            'livros' => $this->livros->buscar($busca, $preco)->all(),
            'totais' => $this->livros->totaisPorAutor()->all(),
        ]);
    }
}
