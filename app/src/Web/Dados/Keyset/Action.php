<?php

declare(strict_types=1);

namespace App\Web\Dados\Keyset;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\KeysetPaginator;
use Yiisoft\Data\Paginator\PageToken;
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

    public function __invoke(
        #[Query('depois')] ?string $depois = null,
        #[Query('antes')] ?string $antes = null,
    ): ResponseInterface {
        $leitor = (new QueryDataReader($this->db->select(['slug', 'titulo', 'ano'])->from('livro')))
            ->withSort(Sort::only(['slug']));

        $paginador = (new KeysetPaginator($leitor))->withPageSize(5);

        if ($depois !== null) {
            $paginador = $paginador->withToken(PageToken::next($depois));
        } elseif ($antes !== null) {
            $paginador = $paginador->withToken(PageToken::previous($antes));
        }

        return $this->viewRenderer->render(__DIR__ . '/template', ['paginador' => $paginador]);
    }
}
