<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Comandos;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $total = $this->db
            ->createCommand('SELECT COUNT(*) FROM livro')
            ->queryScalar();

        $autores = $this->db
            ->createCommand('SELECT nome FROM autor ORDER BY nome')
            ->queryColumn();

        $antigos = $this->db
            ->createCommand('SELECT titulo, ano FROM livro WHERE ano < :ano ORDER BY ano', [':ano' => 1900])
            ->queryAll();

        $livro = $this->db
            ->createCommand('SELECT * FROM {{livro}} WHERE [[slug]] = :slug')
            ->bindValue(':slug', 'dom-casmurro')
            ->queryOne();

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'driver' => $this->db->getDriverName(),
            'total' => $total,
            'autores' => $autores,
            'antigos' => $antigos,
            'livro' => $livro,
        ]);
    }
}
