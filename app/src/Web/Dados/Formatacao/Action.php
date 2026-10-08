<?php

declare(strict_types=1);

namespace App\Web\Dados\Formatacao;

use App\Dados\Formatador;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
        private Formatador $formatador,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $livros = $this->db
            ->select(['titulo', 'preco', 'cadastrado_em'])
            ->from('livro')
            ->orderBy(['cadastrado_em' => SORT_DESC])
            ->limit(5)
            ->all();

        $total = $this->db->select()->from('livro')->sum('preco');

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'livros' => $livros,
            'total' => $total,
            'formatador' => $this->formatador,
        ]);
    }
}
