<?php

declare(strict_types=1);

namespace App\Web\Dados\Ordenacao;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Reader\Sort;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
    ) {}

    public function __invoke(#[Query('ordem')] string $ordem = ''): ResponseInterface
    {
        $query = $this->db
            ->select(['titulo' => 'l.titulo', 'autor' => 'a.nome', 'ano' => 'l.ano', 'preco' => 'l.preco'])
            ->from(['l' => 'livro'])
            ->innerJoin(['a' => 'autor'], 'a.id = l.autor_id');

        $sort = Sort::only([
            'titulo',
            'autor' => [
                'asc' => ['autor' => SORT_ASC, 'ano' => SORT_ASC],
                'desc' => ['autor' => SORT_DESC, 'ano' => SORT_ASC],
            ],
            'ano',
            'preco' => ['default' => 'desc'],
        ])->withOrderString($ordem);

        $leitor = (new QueryDataReader($query))->withSort($sort);

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'sort' => $sort,
            'livros' => $leitor->read(),
        ]);
    }
}
