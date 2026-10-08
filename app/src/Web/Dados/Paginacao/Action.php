<?php

declare(strict_types=1);

namespace App\Web\Dados\Paginacao;

use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\InvalidPageException;
use Yiisoft\Data\Paginator\OffsetPaginator;
use Yiisoft\Data\Paginator\PageToken;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
        private NotFoundHandler $notFoundHandler,
    ) {}

    public function __invoke(ServerRequestInterface $request, #[Query('pagina')] string $pagina = '1'): ResponseInterface
    {
        $query = $this->db
            ->select(['titulo', 'ano'])
            ->from('livro')
            ->orderBy(['titulo' => SORT_ASC]);

        $paginador = (new OffsetPaginator(new QueryDataReader($query)))->withPageSize(5);

        try {
            $paginador = $paginador->withToken(PageToken::next($pagina));
            $livros = iterator_to_array($paginador->read());
        } catch (InvalidPageException) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'paginador' => $paginador,
            'livros' => $livros,
        ]);
    }
}
