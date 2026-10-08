<?php

declare(strict_types=1);

namespace App\Web\Seguranca\Convite;

use App\Seguranca\Cripto\TokenAssinado;
use App\Seguranca\Usuario;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

final readonly class GerarAction
{
    public function __construct(
        private WebViewRenderer $viewRenderer,
        private CurrentUser $currentUser,
        private TokenAssinado $tokenAssinado,
    ) {}

    public function __invoke(): ResponseInterface
    {
        /** @var Usuario $usuario */
        $usuario = $this->currentUser->getIdentity();

        $token = $this->tokenAssinado->gerar(['convidadoPor' => $usuario->nome], validadeEmSegundos: 600);

        return $this->viewRenderer->render(__DIR__ . '/gerado', ['token' => $token]);
    }
}
