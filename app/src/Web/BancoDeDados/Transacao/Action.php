<?php

declare(strict_types=1);

namespace App\Web\BancoDeDados\Transacao;

use App\BancoDeDados\CadastroDeAutor;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\IntegrityException;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class Action
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private ConnectionInterface $db,
        private CadastroDeAutor $cadastro,
    ) {}

    public function __invoke(): ResponseInterface
    {
        $antes = $this->contarAutores();
        $erro = null;

        try {
            // O segundo livro repete um slug que já existe: o insert dele falha.
            $this->cadastro->cadastrar('Cecília Meireles', [
                ['titulo' => 'Romanceiro da Inconfidência', 'slug' => 'romanceiro', 'ano' => 1953, 'preco' => '52.90'],
                ['titulo' => 'Ou Isto ou Aquilo', 'slug' => 'dom-casmurro', 'ano' => 1964, 'preco' => '35.00'],
            ]);
        } catch (IntegrityException $e) {
            $erro = $e->getMessage();
        }

        return $this->viewRenderer->render(__DIR__ . '/template', [
            'antes' => $antes,
            'depois' => $this->contarAutores(),
            'erro' => $erro,
        ]);
    }

    private function contarAutores(): int
    {
        return (int) $this->db->createCommand('SELECT COUNT(*) FROM autor')->queryScalar();
    }
}
