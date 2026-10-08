<?php

declare(strict_types=1);

namespace App\Web\Estrutura\Tarefa;

use App\Estrutura\Tarefa\TarefaRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class ListarAction
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private TarefaRepositoryInterface $tarefas,
    ) {}

    public function __invoke(): ResponseInterface
    {
        return $this->viewRenderer->render(__DIR__ . '/listar', [
            'tarefas' => $this->tarefas->todas(),
        ]);
    }
}
