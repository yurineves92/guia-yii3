<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Convite;

use App\Seguranca\Cripto\TokenAssinado;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Http\Status;
use Yiisoft\Input\Http\Attribute\Parameter\Query;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class AbrirAction
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private TokenAssinado $tokenAssinado,
    ) {}

    public function __invoke(#[Query('token')] string $token = ''): ResponseInterface
    {
        $dados = $this->tokenAssinado->ler($token);

        if ($dados === null) {
            return $this->viewRenderer
                ->render(__DIR__ . '/invalido')
                ->withStatus(Status::FORBIDDEN);
        }

        return $this->viewRenderer->render(__DIR__ . '/aberto', [
            'convidadoPor' => $dados['convidadoPor'],
            'expira' => $dados['expira'],
        ]);
    }
}
