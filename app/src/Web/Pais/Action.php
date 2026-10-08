<?php

declare(strict_types=1);

namespace App\Web\Pais;

use App\Web\NotFound\NotFoundHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Data\Db\QueryDataReader;
use Yiisoft\Data\Paginator\OffsetPaginator;
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

    public function __invoke(ServerRequestInterface $request, #[Query('pagina')] int $pagina = 1): ResponseInterface
    {
        $query = $this->db->select()->from('pais')->orderBy(['nome' => SORT_ASC]);

        $paginator = (new OffsetPaginator(new QueryDataReader($query)))->withPageSize(5);

        if ($pagina < 1 || $pagina > $paginator->getTotalPages()) {
            return $this->notFoundHandler->handle($request);
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'paginator' => $paginator->withCurrentPage($pagina),
        ]);
    }
}
