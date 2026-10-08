<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Busca;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
    ) {}

    public function __invoke(#[Query('q')] string $termo = ''): ResponseInterface
    {
        $query = $this->db
            ->select(['nome', 'codigo'])
            ->from('pais')
            ->where(['like', 'nome', $termo])
            ->orderBy(['nome' => SORT_ASC]);

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'termo' => $termo,
            'paises' => $termo === '' ? [] : $query->all(),
            'sql' => $query->createCommand()->getSql(),
        ]);
    }
}
